<?php

namespace Tests\Feature\Management;

use App\Modules\Identity\Models\User;
use App\Modules\Management\Actions\CreateManagementTransfer;
use App\Modules\Management\Data\ManagementTransferData;
use App\Modules\Management\Models\ManagementTransfer;
use App\Modules\Management\Models\Wallet;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class ManagementTransferDatabaseConstraintTest extends TestCase
{
    use RefreshDatabase;

    public function test_postgresql_defines_transfer_constraints_and_triggers(): void
    {
        $constraints = DB::table('pg_constraint')->whereIn('conname', [
            'management_transfers_amount_positive_integer_pesos',
            'management_transfers_distinct_wallets',
            'management_transfers_transaction_unique',
        ])->pluck('conname')->all();
        $this->assertEqualsCanonicalizing([
            'management_transfers_amount_positive_integer_pesos',
            'management_transfers_distinct_wallets',
            'management_transfers_transaction_unique',
        ], $constraints);

        $triggers = DB::table('pg_trigger')->whereIn('tgname', [
            'management_transfers_validate_source',
            'financial_transactions_require_transfer_source',
            'management_transfers_immutable',
        ])->where('tgisinternal', false)->pluck('tgname')->all();
        $this->assertEqualsCanonicalizing([
            'management_transfers_validate_source',
            'financial_transactions_require_transfer_source',
            'management_transfers_immutable',
        ], $triggers);
    }

    public function test_postgresql_rejects_non_positive_fractional_and_same_wallet_transfers(): void
    {
        $admin = User::factory()->administrator()->create();
        [$source, $destination] = $this->wallets();

        foreach ([
            ['0', $source->id, $destination->id],
            ['-1000', $source->id, $destination->id],
            ['1.50', $source->id, $destination->id],
            ['1000', $source->id, $source->id],
        ] as [$amount, $sourceId, $destinationId]) {
            $this->expectQueryFailure(fn () => $this->insertRawTransfer(
                $admin->id, $sourceId, $destinationId, $amount, -1000, 1000,
            ));
        }

        $this->assertDatabaseCount('management_transfers', 0);
        $this->assertDatabaseCount('financial_transactions', 0);
        $this->assertDatabaseCount('ledger_entries', 0);
    }

    public function test_postgresql_requires_one_source_and_exactly_two_matching_neutral_entries(): void
    {
        $admin = User::factory()->administrator()->create();
        [$source, $destination] = $this->wallets();
        $third = Wallet::query()->create(['name' => 'Ahorro']);

        foreach ([
            [-1000, 999, null],
            [1000, -1000, null],
            [-1000, 1000, $third->id],
        ] as [$sourceAmount, $destinationAmount, $thirdWalletId]) {
            $this->expectQueryFailure(fn () => $this->insertRawTransfer(
                $admin->id, $source->id, $destination->id, '1000', $sourceAmount, $destinationAmount, $thirdWalletId,
            ));
        }

        $this->expectQueryFailure(function () use ($admin, $source, $destination): void {
            DB::transaction(function () use ($admin, $source, $destination): void {
                $transactionId = $this->insertTransaction($admin->id);
                $this->insertEntry($transactionId, $source->id, -1000);
                $this->insertEntry($transactionId, $destination->id, 1000);
                DB::statement('SET CONSTRAINTS financial_transactions_require_transfer_source IMMEDIATE');
            });
        });

        $this->assertDatabaseCount('management_transfers', 0);
        $this->assertDatabaseCount('financial_transactions', 0);
        $this->assertDatabaseCount('ledger_entries', 0);
    }

    public function test_postgresql_requires_transfer_header_type_creator_and_effective_date_to_match_source(): void
    {
        $admin = User::factory()->administrator()->create();
        $otherAdmin = User::factory()->administrator()->create();
        [$source, $destination] = $this->wallets();

        $this->expectQueryFailure(fn () => $this->insertRawTransfer(
            $admin->id, $source->id, $destination->id, '1000', -1000, 1000,
            transferAdminId: $otherAdmin->id,
        ));
        $this->expectQueryFailure(fn () => $this->insertRawTransfer(
            $admin->id, $source->id, $destination->id, '1000', -1000, 1000,
            transferDate: '2026-08-28',
        ));
        $this->expectQueryFailure(fn () => $this->insertRawTransfer(
            $admin->id, $source->id, $destination->id, '1000', -1000, 1000,
            transactionType: 'opening',
        ));

        $this->assertDatabaseCount('management_transfers', 0);
        $this->assertDatabaseCount('financial_transactions', 0);
        $this->assertDatabaseCount('ledger_entries', 0);
    }

    public function test_postgresql_keeps_transfer_immutable_and_transaction_relation_one_to_one(): void
    {
        $admin = User::factory()->administrator()->create();
        [$source, $destination] = $this->wallets();
        $transfer = $this->createTransfer($admin->id, $source->id, $destination->id);

        foreach ([
            fn () => DB::table('management_transfers')->where('id', $transfer->id)->update(['amount' => 2000]),
            fn () => DB::table('management_transfers')->where('id', $transfer->id)->delete(),
            fn () => DB::table('management_transfers')->insert([
                'source_wallet_id' => $source->id,
                'destination_wallet_id' => $destination->id,
                'financial_transaction_id' => $transfer->financial_transaction_id,
                'amount' => 1000,
                'effective_date' => '2026-08-27',
                'created_by' => $admin->id,
                'created_at' => now(),
                'updated_at' => now(),
            ]),
        ] as $mutation) {
            $this->expectQueryFailure(fn () => DB::transaction($mutation));
        }

        $this->assertDatabaseCount('management_transfers', 1);
        $this->assertSame(1000, (int) $transfer->fresh()->amount);
    }

    /** @return array{Wallet, Wallet} */
    private function wallets(): array
    {
        return [Wallet::query()->create(['name' => 'Banco']), Wallet::query()->create(['name' => 'Caja'])];
    }

    private function insertRawTransfer(
        int $adminId,
        int $sourceWalletId,
        int $destinationWalletId,
        string $amount,
        int $sourceEntryAmount,
        int $destinationEntryAmount,
        ?int $thirdWalletId = null,
        ?int $transferAdminId = null,
        string $transferDate = '2026-08-27',
        string $transactionType = 'transfer',
    ): void {
        DB::transaction(function () use ($adminId, $sourceWalletId, $destinationWalletId, $amount, $sourceEntryAmount, $destinationEntryAmount, $thirdWalletId, $transferAdminId, $transferDate, $transactionType): void {
            $transactionId = $this->insertTransaction($adminId, $transactionType);
            $this->insertEntry($transactionId, $sourceWalletId, $sourceEntryAmount);
            if ($destinationWalletId !== $sourceWalletId) {
                $this->insertEntry($transactionId, $destinationWalletId, $destinationEntryAmount);
            }
            if ($thirdWalletId !== null) {
                $this->insertEntry($transactionId, $thirdWalletId, 1);
            }
            DB::table('management_transfers')->insert([
                'source_wallet_id' => $sourceWalletId,
                'destination_wallet_id' => $destinationWalletId,
                'financial_transaction_id' => $transactionId,
                'amount' => $amount,
                'effective_date' => $transferDate,
                'description' => null,
                'created_by' => $transferAdminId ?? $adminId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            DB::statement('SET CONSTRAINTS ALL IMMEDIATE');
        });
    }

    private function insertTransaction(int $adminId, string $type = 'transfer'): int
    {
        return DB::table('financial_transactions')->insertGetId([
            'type' => $type,
            'description' => null,
            'effective_date' => '2026-08-27',
            'created_by' => $adminId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function insertEntry(int $transactionId, int $walletId, int $amount): void
    {
        DB::table('ledger_entries')->insert([
            'financial_transaction_id' => $transactionId,
            'wallet_id' => $walletId,
            'amount' => $amount,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function createTransfer(int $adminId, int $sourceWalletId, int $destinationWalletId): ManagementTransfer
    {
        return app(CreateManagementTransfer::class)->execute(new ManagementTransferData(
            sourceWalletId: $sourceWalletId,
            destinationWalletId: $destinationWalletId,
            amount: 1000,
            effectiveDate: CarbonImmutable::parse('2026-08-27'),
            description: null,
        ), $adminId);
    }

    private function expectQueryFailure(callable $operation): void
    {
        try {
            $operation();
            $this->fail('Expected PostgreSQL to reject the operation.');
        } catch (QueryException) {
            $this->addToAssertionCount(1);
        }
    }
}
