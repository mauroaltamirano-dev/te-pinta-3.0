<?php

namespace Tests\Feature\Management;

use App\Modules\Identity\Models\User;
use App\Modules\Management\Actions\CreateManualTransaction;
use App\Modules\Management\Data\ManualTransactionData;
use App\Modules\Management\Enums\FinancialTransactionType;
use App\Modules\Management\Models\Wallet;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class ManagementDatabaseConstraintTest extends TestCase
{
    use RefreshDatabase;

    public function test_postgresql_defines_management_constraints_and_immutable_triggers(): void
    {
        $constraints = DB::table('pg_constraint')->whereIn('conname', [
            'wallets_sort_order_nonnegative',
            'financial_transactions_type_valid',
            'financial_transactions_reversal_shape_valid',
            'financial_transactions_single_reversal_unique',
            'ledger_entries_amount_nonzero_integer_pesos',
            'ledger_entries_transaction_wallet_unique',
        ])->pluck('conname')->all();
        $this->assertEqualsCanonicalizing([
            'wallets_sort_order_nonnegative',
            'financial_transactions_type_valid',
            'financial_transactions_reversal_shape_valid',
            'financial_transactions_single_reversal_unique',
            'ledger_entries_amount_nonzero_integer_pesos',
            'ledger_entries_transaction_wallet_unique',
        ], $constraints);

        $triggers = DB::table('pg_trigger')->whereIn('tgname', [
            'financial_transactions_immutable', 'ledger_entries_immutable', 'financial_transactions_require_entries',
        ])->where('tgisinternal', false)->pluck('tgname')->all();
        $this->assertEqualsCanonicalizing([
            'financial_transactions_immutable', 'ledger_entries_immutable', 'financial_transactions_require_entries',
        ], $triggers);
    }

    public function test_postgresql_rejects_zero_fractional_and_orphaned_history(): void
    {
        $admin = User::factory()->administrator()->create();
        $wallet = Wallet::query()->create(['name' => 'Caja']);

        foreach (['0', '1.50'] as $amount) {
            try {
                DB::transaction(function () use ($admin, $wallet, $amount): void {
                    $id = DB::table('financial_transactions')->insertGetId([
                        'type' => 'manual_income', 'effective_date' => '2026-08-26', 'created_by' => $admin->id,
                        'created_at' => now(), 'updated_at' => now(),
                    ]);
                    DB::table('ledger_entries')->insert([
                        'financial_transaction_id' => $id, 'wallet_id' => $wallet->id, 'amount' => $amount,
                        'created_at' => now(), 'updated_at' => now(),
                    ]);
                });
                $this->fail("Expected amount {$amount} to fail.");
            } catch (QueryException) {
                $this->addToAssertionCount(1);
            }
        }

        try {
            DB::transaction(function () use ($admin): void {
                DB::table('financial_transactions')->insert([
                    'type' => 'manual_income', 'effective_date' => '2026-08-26', 'created_by' => $admin->id,
                    'created_at' => now(), 'updated_at' => now(),
                ]);
                DB::statement('SET CONSTRAINTS financial_transactions_require_entries IMMEDIATE');
            });
            $this->fail('Expected a transaction without entries to fail at commit.');
        } catch (QueryException) {
            $this->addToAssertionCount(1);
        }
    }

    public function test_postgresql_prevents_updates_and_deletes_of_confirmed_history(): void
    {
        $admin = User::factory()->administrator()->create();
        $wallet = Wallet::query()->create(['name' => 'Caja']);
        $transaction = app(CreateManualTransaction::class)->execute(new ManualTransactionData(
            $wallet->id, FinancialTransactionType::Opening, 5000, CarbonImmutable::parse('2026-08-26'), 'Opening',
        ), $admin->id);
        $entry = $transaction->entries()->firstOrFail();

        foreach ([
            fn () => DB::table('financial_transactions')->where('id', $transaction->id)->update(['description' => 'Changed']),
            fn () => DB::table('financial_transactions')->where('id', $transaction->id)->delete(),
            fn () => DB::table('ledger_entries')->where('id', $entry->id)->update(['amount' => 6000]),
            fn () => DB::table('ledger_entries')->where('id', $entry->id)->delete(),
        ] as $mutation) {
            try {
                DB::transaction($mutation);
                $this->fail('Expected immutable financial history to reject mutation.');
            } catch (QueryException) {
                $this->addToAssertionCount(1);
            }
        }

        $this->assertSame('Opening', $transaction->fresh()->description);
        $this->assertSame(5000, (int) $entry->fresh()->amount);
    }

    public function test_wallet_with_history_cannot_be_physically_deleted(): void
    {
        $admin = User::factory()->administrator()->create();
        $wallet = Wallet::query()->create(['name' => 'Caja']);
        app(CreateManualTransaction::class)->execute(new ManualTransactionData(
            $wallet->id, FinancialTransactionType::Opening, 5000, CarbonImmutable::parse('2026-08-26'), null,
        ), $admin->id);

        $this->expectException(QueryException::class);
        DB::table('wallets')->where('id', $wallet->id)->delete();
    }
}
