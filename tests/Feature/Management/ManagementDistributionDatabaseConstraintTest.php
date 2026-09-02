<?php

namespace Tests\Feature\Management;

use App\Modules\Customers\Models\Customer;
use App\Modules\Identity\Models\User;
use App\Modules\Management\Actions\CreateManagementDistribution;
use App\Modules\Management\Data\ManagementDistributionData;
use App\Modules\Management\Models\DistributionRule;
use App\Modules\Management\Models\Wallet;
use App\Modules\Orders\Models\Order;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class ManagementDistributionDatabaseConstraintTest extends TestCase
{
    use RefreshDatabase;

    public function test_postgresql_defines_distribution_constraints_and_triggers(): void
    {
        $constraints = DB::table('pg_constraint')->whereIn('conname', [
            'management_distribution_rule_versions_number_unique',
            'management_distribution_rule_allocations_percentage_valid',
            'management_distributions_amount_positive_integer_pesos',
            'management_distributions_transaction_unique',
            'management_distributions_source_wallet_id_foreign',
            'management_distributions_management_order_payment_id_foreign',
            'management_distributions_order_payment_unique',
            'management_distribution_allocations_amount_positive_integer',
        ])->pluck('conname')->all();
        $this->assertEqualsCanonicalizing([
            'management_distribution_rule_versions_number_unique',
            'management_distribution_rule_allocations_percentage_valid',
            'management_distributions_amount_positive_integer_pesos',
            'management_distributions_transaction_unique',
            'management_distributions_source_wallet_id_foreign',
            'management_distributions_management_order_payment_id_foreign',
            'management_distributions_order_payment_unique',
            'management_distribution_allocations_amount_positive_integer',
        ], $constraints);

        $triggers = DB::table('pg_trigger')->whereIn('tgname', [
            'management_distribution_rule_versions_validate',
            'management_distribution_rule_allocations_validate',
            'management_distributions_validate_source',
            'management_distribution_allocations_validate',
            'financial_transactions_require_distribution_source',
            'management_distribution_ledger_entries_validate',
            'management_distribution_rule_versions_immutable',
            'management_distribution_rule_allocations_immutable',
            'management_distributions_immutable',
            'management_distributions_validate_order_payment',
            'management_distribution_allocations_immutable',
        ])->where('tgisinternal', false)->pluck('tgname')->all();
        $this->assertEqualsCanonicalizing([
            'management_distribution_rule_versions_validate',
            'management_distribution_rule_allocations_validate',
            'management_distributions_validate_source',
            'management_distribution_allocations_validate',
            'financial_transactions_require_distribution_source',
            'management_distribution_ledger_entries_validate',
            'management_distribution_rule_versions_immutable',
            'management_distribution_rule_allocations_immutable',
            'management_distributions_immutable',
            'management_distributions_validate_order_payment',
            'management_distribution_allocations_immutable',
        ], $triggers);

        $this->assertSame(1, DB::table('pg_indexes')
            ->where('indexname', 'management_distribution_rules_single_active_unique')
            ->count());
    }

    public function test_postgresql_allows_only_one_globally_active_distribution_rule(): void
    {
        $admin = User::factory()->administrator()->create();
        DistributionRule::query()->create(['name' => 'A', 'active' => true, 'created_by' => $admin->id]);

        $this->expectException(QueryException::class);
        DistributionRule::query()->create(['name' => 'B', 'active' => true, 'created_by' => $admin->id]);
    }

    public function test_postgresql_rejects_incomplete_rules_zero_shares_and_archived_wallets(): void
    {
        $admin = User::factory()->administrator()->create();
        $wallets = $this->wallets();
        $rule = $this->insertRule($admin->id);

        foreach ([[5000, 3000, 1999], [5000, 5000, 0]] as $bps) {
            $this->expectQueryFailure(fn () => $this->insertVersion($rule->id, $admin->id, $wallets, $bps));
        }
        $wallets[2]->update(['archived_at' => now()]);
        $this->expectQueryFailure(fn () => $this->insertVersion($rule->id, $admin->id, $wallets, [5000, 3000, 2000]));
        $this->assertDatabaseCount('management_distribution_rule_versions', 0);
    }

    public function test_postgresql_keeps_versions_allocations_distributions_and_snapshots_immutable(): void
    {
        $admin = User::factory()->administrator()->create();
        $wallets = $this->wallets();
        $rule = $this->insertRule($admin->id);
        $versionId = $this->insertVersion($rule->id, $admin->id, $wallets, [5000, 3000, 2000]);
        $distribution = app(CreateManagementDistribution::class)->execute(new ManagementDistributionData(
            $this->sourceWallet()->id, $versionId, 10000, CarbonImmutable::parse('2026-08-27'), null,
        ), $admin->id);
        $extraWallet = Wallet::query()->create(['name' => 'Extra']);

        foreach ([
            fn () => DB::table('management_distribution_rule_versions')->where('id', $versionId)->update(['version_number' => 2]),
            fn () => DB::table('management_distribution_rule_allocations')->where('rule_version_id', $versionId)->limit(1)->delete(),
            fn () => DB::table('management_distributions')->where('id', $distribution->id)->update(['amount' => 9999]),
            fn () => DB::table('management_distribution_allocations')->where('management_distribution_id', $distribution->id)->limit(1)->update(['wallet_name' => 'Changed']),
            function () use ($distribution, $extraWallet): void {
                DB::table('ledger_entries')->insert([
                    'financial_transaction_id' => $distribution->financial_transaction_id,
                    'wallet_id' => $extraWallet->id,
                    'amount' => 1,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                DB::statement('SET CONSTRAINTS ALL IMMEDIATE');
            },
        ] as $mutation) {
            $this->expectQueryFailure(fn () => DB::transaction($mutation));
        }
    }

    public function test_postgresql_requires_distribution_source_and_exact_matching_rounded_entries(): void
    {
        $admin = User::factory()->administrator()->create();
        $wallets = $this->wallets();
        $rule = $this->insertRule($admin->id);
        $versionId = $this->insertVersion($rule->id, $admin->id, $wallets, [3300, 3300, 3400]);

        $this->expectQueryFailure(function () use ($admin, $versionId): void {
            DB::transaction(function () use ($admin, $versionId): void {
                DB::table('management_distributions')->insert([
                    'rule_version_id' => $versionId,
                    'financial_transaction_id' => $this->insertTransaction($admin->id),
                    'amount' => 10,
                    'effective_date' => '2026-08-27',
                    'created_by' => $admin->id,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            });
        });

        $this->expectQueryFailure(function () use ($admin, $wallets): void {
            DB::transaction(function () use ($admin, $wallets): void {
                $transactionId = $this->insertTransaction($admin->id);
                DB::table('ledger_entries')->insert([
                    'financial_transaction_id' => $transactionId, 'wallet_id' => $wallets[0]->id,
                    'amount' => 10, 'created_at' => now(), 'updated_at' => now(),
                ]);
                DB::statement('SET CONSTRAINTS ALL IMMEDIATE');
            });
        });

        $this->expectQueryFailure(fn () => $this->insertRawDistribution(
            $admin->id, $versionId, $wallets, [3, 3, 3], [3, 3, 3], 10,
        ));
        $this->expectQueryFailure(fn () => $this->insertRawDistribution(
            $admin->id, $versionId, $wallets, [3, 3, 4], [3, 3, 4], 10,
            transactionType: 'manual_income',
        ));
        $this->expectQueryFailure(fn () => $this->insertRawDistribution(
            $admin->id, $versionId, $wallets, [3, 3, 4], [3, 3, 4], 10,
            sourceDebit: -9,
        ));
        $this->expectQueryFailure(fn () => $this->insertRawDistribution(
            $admin->id, $versionId, $wallets, [3, 3, 4], [3, 3, 4], 10,
            source: $wallets[0],
        ));

        $archivedSource = Wallet::query()->create(['name' => 'Archived source', 'archived_at' => now()]);
        $this->expectQueryFailure(fn () => $this->insertRawDistribution(
            $admin->id, $versionId, $wallets, [3, 3, 4], [3, 3, 4], 10,
            source: $archivedSource,
        ));
        $this->assertDatabaseCount('management_distributions', 0);
        $this->assertDatabaseCount('financial_transactions', 0);
    }

    public function test_postgresql_rejects_order_payment_links_with_different_source_amount_or_effective_date(): void
    {
        $admin = User::factory()->administrator()->create();
        $paymentWallet = Wallet::query()->create(['name' => 'Caja', 'is_order_payment_default' => true]);
        $paymentId = $this->insertOrderPayment($admin->id, $paymentWallet);
        $wallets = $this->wallets();
        $rule = $this->insertRule($admin->id);
        $versionId = $this->insertVersion($rule->id, $admin->id, $wallets, [3300, 3300, 3400]);

        foreach ([
            fn () => $this->insertRawDistribution(
                $admin->id, $versionId, $wallets, [3, 3, 4], [3, 3, 4], 10,
                source: $this->sourceWallet(), managementOrderPaymentId: $paymentId,
            ),
            fn () => $this->insertRawDistribution(
                $admin->id, $versionId, $wallets, [3, 3, 5], [3, 3, 5], 11,
                source: $paymentWallet, managementOrderPaymentId: $paymentId,
            ),
            fn () => $this->insertRawDistribution(
                $admin->id, $versionId, $wallets, [3, 3, 4], [3, 3, 4], 10,
                source: $paymentWallet, managementOrderPaymentId: $paymentId,
                effectiveDate: '2026-08-28',
            ),
        ] as $invalidLink) {
            $this->expectQueryFailure($invalidLink);
        }
    }

    public function test_postgresql_allows_at_most_one_distribution_for_each_order_payment(): void
    {
        $admin = User::factory()->administrator()->create();
        $paymentWallet = Wallet::query()->create(['name' => 'Caja', 'is_order_payment_default' => true]);
        $paymentId = $this->insertOrderPayment($admin->id, $paymentWallet);
        $wallets = $this->wallets();
        $rule = $this->insertRule($admin->id);
        $versionId = $this->insertVersion($rule->id, $admin->id, $wallets, [3300, 3300, 3400]);

        $this->insertRawDistribution(
            $admin->id, $versionId, $wallets, [3, 3, 4], [3, 3, 4], 10,
            source: $paymentWallet, managementOrderPaymentId: $paymentId,
        );
        $this->expectQueryFailure(fn () => $this->insertRawDistribution(
            $admin->id, $versionId, $wallets, [3, 3, 4], [3, 3, 4], 10,
            source: $paymentWallet, managementOrderPaymentId: $paymentId,
        ));

        $this->assertSame(1, DB::table('management_distributions')
            ->where('management_order_payment_id', $paymentId)
            ->count());
    }

    /** @return list<Wallet> */
    private function wallets(): array
    {
        $wallets = [];
        foreach (['Operación', 'Ganancia', 'Reserva'] as $name) {
            $wallets[] = Wallet::query()->create(['name' => $name]);
        }

        return $wallets;
    }

    private function insertRule(int $adminId): DistributionRule
    {
        return DistributionRule::query()->create(['name' => 'Negocio', 'active' => true, 'created_by' => $adminId]);
    }

    /**
     * @param  list<Wallet>  $wallets
     * @param  list<int>  $bps
     */
    private function insertVersion(int $ruleId, int $adminId, array $wallets, array $bps): int
    {
        return DB::transaction(function () use ($ruleId, $adminId, $wallets, $bps): int {
            $versionId = DB::table('management_distribution_rule_versions')->insertGetId([
                'distribution_rule_id' => $ruleId, 'version_number' => 1, 'created_by' => $adminId, 'created_at' => now(),
            ]);
            foreach ($wallets as $index => $wallet) {
                DB::table('management_distribution_rule_allocations')->insert([
                    'rule_version_id' => $versionId, 'wallet_id' => $wallet->id,
                    'percentage_bps' => $bps[$index], 'sort_order' => $index, 'created_at' => now(),
                ]);
            }
            DB::statement('SET CONSTRAINTS ALL IMMEDIATE');
            DB::statement('SET CONSTRAINTS ALL DEFERRED');

            return $versionId;
        });
    }

    /**
     * @param  list<Wallet>  $wallets
     * @param  list<int>  $entryAmounts
     * @param  list<int>  $snapshotAmounts
     */
    private function insertRawDistribution(int $adminId, int $versionId, array $wallets, array $entryAmounts, array $snapshotAmounts, int $amount, string $transactionType = 'distribution', ?Wallet $source = null, ?int $sourceDebit = null, ?int $managementOrderPaymentId = null, string $effectiveDate = '2026-08-27'): void
    {
        $source ??= $this->sourceWallet();
        DB::transaction(function () use ($adminId, $versionId, $wallets, $entryAmounts, $snapshotAmounts, $amount, $transactionType, $source, $sourceDebit, $managementOrderPaymentId, $effectiveDate): void {
            $transactionId = $this->insertTransaction($adminId, $transactionType, $effectiveDate);
            DB::table('ledger_entries')->insert([
                'financial_transaction_id' => $transactionId, 'wallet_id' => $source->id,
                'amount' => $sourceDebit ?? -$amount, 'created_at' => now(), 'updated_at' => now(),
            ]);
            foreach ($wallets as $index => $wallet) {
                DB::table('ledger_entries')->insert([
                    'financial_transaction_id' => $transactionId, 'wallet_id' => $wallet->id,
                    'amount' => $entryAmounts[$index], 'created_at' => now(), 'updated_at' => now(),
                ]);
            }
            $distributionId = DB::table('management_distributions')->insertGetId([
                'source_wallet_id' => $source->id,
                'management_order_payment_id' => $managementOrderPaymentId,
                'rule_version_id' => $versionId, 'financial_transaction_id' => $transactionId,
                'amount' => $amount, 'effective_date' => $effectiveDate, 'description' => null,
                'created_by' => $adminId, 'created_at' => now(), 'updated_at' => now(),
            ]);
            $bps = [3300, 3300, 3400];
            foreach ($wallets as $index => $wallet) {
                DB::table('management_distribution_allocations')->insert([
                    'management_distribution_id' => $distributionId, 'wallet_id' => $wallet->id,
                    'wallet_name' => $wallet->name, 'percentage_bps' => $bps[$index],
                    'amount' => $snapshotAmounts[$index], 'sort_order' => $index, 'created_at' => now(),
                ]);
            }
            DB::statement('SET CONSTRAINTS ALL IMMEDIATE');
        });
    }

    private function insertOrderPayment(int $adminId, Wallet $wallet): int
    {
        $customer = Customer::query()->create(['name' => 'Order customer']);
        $order = Order::query()->create([
            'customer_id' => $customer->id,
            'source' => 'admin',
            'customer_name_snapshot' => $customer->name,
            'status' => 'waiting',
            'payment_status' => 'paid',
            'payment_method' => 'cash',
            'condition' => 'raw',
            'delivery_type' => 'pickup',
            'delivery_period' => 'night',
            'delivery_date' => '2026-08-27',
            'items_cooked_subtotal' => 10,
            'raw_discount' => 0,
            'items_subtotal' => 10,
            'toppings_subtotal' => 0,
            'shipping_fee_snapshot' => 0,
            'combined_dozen_savings' => 0,
            'discount_amount' => 0,
            'total' => 10,
        ]);

        return DB::transaction(function () use ($adminId, $wallet, $order): int {
            $transactionId = DB::table('financial_transactions')->insertGetId([
                'type' => 'order_payment', 'description' => null, 'effective_date' => '2026-08-27',
                'created_by' => $adminId, 'created_at' => now(), 'updated_at' => now(),
            ]);
            DB::table('ledger_entries')->insert([
                'financial_transaction_id' => $transactionId,
                'wallet_id' => $wallet->id,
                'amount' => 10,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $paymentId = DB::table('management_order_payments')->insertGetId([
                'order_id' => $order->id,
                'destination_wallet_id' => $wallet->id,
                'financial_transaction_id' => $transactionId,
                'amount' => 10,
                'effective_date' => '2026-08-27',
                'created_by' => $adminId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            DB::statement('SET CONSTRAINTS ALL IMMEDIATE');
            DB::statement('SET CONSTRAINTS ALL DEFERRED');

            return $paymentId;
        });
    }

    private function sourceWallet(): Wallet
    {
        return Wallet::query()->firstOrCreate(['name' => 'Caja General']);
    }

    private function insertTransaction(int $adminId, string $type = 'distribution', string $effectiveDate = '2026-08-27'): int
    {
        return DB::table('financial_transactions')->insertGetId([
            'type' => $type, 'description' => null, 'effective_date' => $effectiveDate,
            'created_by' => $adminId, 'created_at' => now(), 'updated_at' => now(),
        ]);
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
