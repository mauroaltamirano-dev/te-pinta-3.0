<?php

namespace Tests\Feature\Management;

use App\Modules\Identity\Models\User;
use App\Modules\Management\Actions\CreateManagementOutflow;
use App\Modules\Management\Data\ManagementOutflowData;
use App\Modules\Management\Enums\ManagementOutflowType;
use App\Modules\Management\Models\ManagementOutflow;
use App\Modules\Management\Models\ManagementOutflowCategory;
use App\Modules\Management\Models\Wallet;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class ManagementOutflowDatabaseConstraintTest extends TestCase
{
    use RefreshDatabase;

    public function test_postgresql_defines_outflow_constraints_and_triggers(): void
    {
        $constraints = DB::table('pg_constraint')->whereIn('conname', [
            'management_outflow_categories_type_valid',
            'management_outflow_categories_sort_order_nonnegative',
            'management_outflow_categories_type_code_unique',
            'management_outflows_type_valid',
            'management_outflows_amount_positive_integer_pesos',
            'management_outflows_transaction_unique',
        ])->pluck('conname')->all();
        $this->assertEqualsCanonicalizing([
            'management_outflow_categories_type_valid',
            'management_outflow_categories_sort_order_nonnegative',
            'management_outflow_categories_type_code_unique',
            'management_outflows_type_valid',
            'management_outflows_amount_positive_integer_pesos',
            'management_outflows_transaction_unique',
        ], $constraints);

        $triggers = DB::table('pg_trigger')->whereIn('tgname', [
            'management_outflows_validate_source',
            'financial_transactions_require_outflow_source',
            'management_outflows_immutable',
            'management_outflow_categories_identity_immutable',
        ])->where('tgisinternal', false)->pluck('tgname')->all();
        $this->assertEqualsCanonicalizing([
            'management_outflows_validate_source',
            'financial_transactions_require_outflow_source',
            'management_outflows_immutable',
            'management_outflow_categories_identity_immutable',
        ], $triggers);
    }

    public function test_postgresql_rejects_invalid_outflow_types_and_non_positive_or_fractional_amounts(): void
    {
        $admin = User::factory()->administrator()->create();
        $wallet = Wallet::query()->create(['name' => 'Caja']);
        $category = $this->category(ManagementOutflowType::Purchase, 'raw_material');

        foreach ([
            ['invalid', '1000'],
            ['purchase', '0'],
            ['purchase', '-1000'],
            ['purchase', '1.50'],
        ] as [$type, $amount]) {
            $this->expectQueryFailure(fn () => $this->insertRawOutflow(
                $admin->id, $wallet->id, $category->id, $type, $amount, -1000,
            ));
        }

        $this->assertDatabaseCount('management_outflows', 0);
        $this->assertDatabaseCount('financial_transactions', 0);
        $this->assertDatabaseCount('ledger_entries', 0);
    }

    public function test_postgresql_requires_one_consistent_source_and_one_matching_entry(): void
    {
        $admin = User::factory()->administrator()->create();
        $wallet = Wallet::query()->create(['name' => 'Caja']);
        $secondWallet = Wallet::query()->create(['name' => 'Banco']);
        $purchase = $this->category(ManagementOutflowType::Purchase, 'raw_material');
        $expense = $this->category(ManagementOutflowType::Expense, 'services');

        $this->expectQueryFailure(fn () => $this->insertRawOutflow(
            $admin->id, $wallet->id, $expense->id, 'purchase', '1000', -1000,
        ));
        $this->expectQueryFailure(fn () => $this->insertRawOutflow(
            $admin->id, $wallet->id, $purchase->id, 'purchase', '1000', 1000,
        ));
        $this->expectQueryFailure(fn () => $this->insertRawOutflow(
            $admin->id, $wallet->id, $purchase->id, 'purchase', '1000', -1000, $secondWallet->id,
        ));

        $this->expectQueryFailure(function () use ($admin, $wallet): void {
            DB::transaction(function () use ($admin, $wallet): void {
                $transactionId = DB::table('financial_transactions')->insertGetId([
                    'type' => 'management_outflow',
                    'description' => 'Orphan',
                    'effective_date' => '2026-08-26',
                    'created_by' => $admin->id,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                DB::table('ledger_entries')->insert([
                    'financial_transaction_id' => $transactionId,
                    'wallet_id' => $wallet->id,
                    'amount' => -1000,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                DB::statement('SET CONSTRAINTS financial_transactions_require_outflow_source IMMEDIATE');
            });
        });

        $this->assertDatabaseCount('management_outflows', 0);
        $this->assertDatabaseCount('financial_transactions', 0);
        $this->assertDatabaseCount('ledger_entries', 0);
    }

    public function test_postgresql_makes_outflows_immutable_and_keeps_the_transaction_one_to_one(): void
    {
        $admin = User::factory()->administrator()->create();
        $wallet = Wallet::query()->create(['name' => 'Caja']);
        $outflow = $this->createOutflow($admin->id, $wallet->id);

        foreach ([
            fn () => DB::table('management_outflows')->where('id', $outflow->id)->update(['amount' => 2000]),
            fn () => DB::table('management_outflows')->where('id', $outflow->id)->delete(),
            fn () => DB::table('management_outflows')->insert([
                'type' => 'purchase',
                'category_id' => $outflow->category_id,
                'wallet_id' => $wallet->id,
                'financial_transaction_id' => $outflow->financial_transaction_id,
                'description' => 'Duplicate',
                'amount' => 1000,
                'effective_date' => '2026-08-26',
                'created_by' => $admin->id,
                'created_at' => now(),
                'updated_at' => now(),
            ]),
        ] as $mutation) {
            $this->expectQueryFailure(fn () => DB::transaction($mutation));
        }

        $this->assertDatabaseCount('management_outflows', 1);
        $this->assertSame(1000, (int) $outflow->fresh()->amount);
    }

    public function test_category_identity_is_immutable_while_presentation_and_lifecycle_can_change_and_used_category_cannot_be_deleted(): void
    {
        $admin = User::factory()->administrator()->create();
        $wallet = Wallet::query()->create(['name' => 'Caja']);
        $outflow = $this->createOutflow($admin->id, $wallet->id);
        $category = $outflow->category;

        DB::table('management_outflow_categories')->where('id', $category->id)->update([
            'name' => 'Materia prima actualizada',
            'is_active' => false,
            'sort_order' => 99,
        ]);
        $this->assertDatabaseHas('management_outflow_categories', [
            'id' => $category->id,
            'name' => 'Materia prima actualizada',
            'is_active' => false,
            'sort_order' => 99,
        ]);

        foreach ([
            fn () => DB::table('management_outflow_categories')->where('id', $category->id)->update(['code' => 'changed']),
            fn () => DB::table('management_outflow_categories')->where('id', $category->id)->update(['type' => 'expense']),
            fn () => DB::table('management_outflow_categories')->where('id', $category->id)->delete(),
        ] as $mutation) {
            $this->expectQueryFailure(fn () => DB::transaction($mutation));
        }

        $this->assertDatabaseHas('management_outflows', ['id' => $outflow->id, 'category_id' => $category->id]);
    }

    private function insertRawOutflow(
        int $adminId,
        int $walletId,
        int $categoryId,
        string $type,
        string $amount,
        int $entryAmount,
        ?int $secondWalletId = null,
    ): void {
        DB::transaction(function () use ($adminId, $walletId, $categoryId, $type, $amount, $entryAmount, $secondWalletId): void {
            $transactionId = DB::table('financial_transactions')->insertGetId([
                'type' => 'management_outflow',
                'description' => 'Raw source',
                'effective_date' => '2026-08-26',
                'created_by' => $adminId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            DB::table('ledger_entries')->insert([
                'financial_transaction_id' => $transactionId,
                'wallet_id' => $walletId,
                'amount' => $entryAmount,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            if ($secondWalletId !== null) {
                DB::table('ledger_entries')->insert([
                    'financial_transaction_id' => $transactionId,
                    'wallet_id' => $secondWalletId,
                    'amount' => -1,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
            DB::table('management_outflows')->insert([
                'type' => $type,
                'category_id' => $categoryId,
                'wallet_id' => $walletId,
                'financial_transaction_id' => $transactionId,
                'description' => 'Raw source',
                'amount' => $amount,
                'effective_date' => '2026-08-26',
                'created_by' => $adminId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            DB::statement('SET CONSTRAINTS ALL IMMEDIATE');
        });
    }

    private function createOutflow(int $adminId, int $walletId): ManagementOutflow
    {
        $category = $this->category(ManagementOutflowType::Purchase, 'raw_material');

        return app(CreateManagementOutflow::class)->execute(new ManagementOutflowData(
            type: ManagementOutflowType::Purchase,
            categoryId: $category->id,
            walletId: $walletId,
            description: 'Harina',
            counterparty: null,
            amount: 1000,
            effectiveDate: CarbonImmutable::parse('2026-08-26'),
        ), $adminId);
    }

    private function category(ManagementOutflowType $type, string $code): ManagementOutflowCategory
    {
        return ManagementOutflowCategory::query()->where('type', $type)->where('code', $code)->sole();
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
