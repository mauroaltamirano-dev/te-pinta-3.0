<?php

namespace Tests\Feature\Management;

use App\Modules\Customers\Models\Customer;
use App\Modules\Identity\Models\User;
use App\Modules\Management\Actions\CreateDistributionRule;
use App\Modules\Management\Actions\CreateManagementOutflow;
use App\Modules\Management\Actions\CreateManagementTransfer;
use App\Modules\Management\Actions\CreateManualTransaction;
use App\Modules\Management\Actions\ReverseFinancialTransaction;
use App\Modules\Management\Data\DistributionAllocationData;
use App\Modules\Management\Data\DistributionRuleData;
use App\Modules\Management\Data\ManagementOutflowData;
use App\Modules\Management\Data\ManagementTransferData;
use App\Modules\Management\Data\ManualTransactionData;
use App\Modules\Management\Data\ReversalData;
use App\Modules\Management\Enums\FinancialTransactionType;
use App\Modules\Management\Enums\ManagementOutflowType;
use App\Modules\Management\Models\FinancialTransaction;
use App\Modules\Management\Models\ManagementDistribution;
use App\Modules\Management\Models\ManagementOrderPayment;
use App\Modules\Management\Models\ManagementOutflow;
use App\Modules\Management\Models\ManagementOutflowCategory;
use App\Modules\Management\Models\ManagementTransfer;
use App\Modules\Management\Models\Wallet;
use App\Modules\Orders\Actions\UpdateOrderPaymentStatus;
use App\Modules\Orders\Enums\PaymentMethod;
use App\Modules\Orders\Enums\PaymentStatus;
use App\Modules\Orders\Models\Order;
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

final class ManagementAuditTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Wallet $incomeWallet;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        CarbonImmutable::setTestNow('2026-08-29 12:00:00');
        $this->admin = User::factory()->administrator()->create();
        $this->incomeWallet = Wallet::query()->create([
            'name' => 'Caja General',
            'is_order_payment_default' => true,
        ]);
    }

    public function test_only_an_administrator_can_view_the_read_only_audit(): void
    {
        $user = User::factory()->create();

        $this->get('/admin/management/audit')->assertRedirect('/login');
        $this->actingAs($user)->get('/admin/management/audit')->assertForbidden();
        $this->actingAs($this->admin)->get('/admin/management/audit')->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('admin/management/audit')->has('audit'));
    }

    public function test_wallet_balances_are_derived_only_from_the_ledger_and_a_get_mutates_nothing(): void
    {
        $this->manualMovement(FinancialTransactionType::ManualIncome, 15_000, '2026-08-20');
        $this->manualMovement(FinancialTransactionType::ManualExpense, 4_000, '2026-08-21');
        $before = $this->financialSnapshot();

        $this->actingAs($this->admin)->get('/admin/management/audit?period=30_days')
            ->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('audit.wallet_balances.0.wallet_id', $this->incomeWallet->id)
            ->where('audit.wallet_balances.0.derived_balance', 11_000)
            ->where('audit.wallet_balances.0.transaction_count', 2)
            ->where('audit.wallet_balances.0.last_transaction_at', '2026-08-21')
            ->where('audit.wallet_balances.0.status', 'ok'));

        $this->assertSame($before, $this->financialSnapshot());
        $this->assertFalse(array_key_exists('balance', $this->incomeWallet->fresh()->getAttributes()));
    }

    public function test_consistent_payment_is_pending_without_a_rule_and_legacy_paid_orders_are_excluded(): void
    {
        $paymentOrder = $this->confirmPayment(15_000);
        $payment = ManagementOrderPayment::query()->sole();
        $legacy = $this->order(9_000, 'paid');

        $this->actingAs($this->admin)->get('/admin/management/audit?type=pending_distribution&period=30_days')
            ->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('audit.summary.pending', 1)
            ->has('audit.findings.data', 1)
            ->where('audit.findings.data.0.type', 'pending_distribution')
            ->where('audit.findings.data.0.order_public_id', $paymentOrder->public_id)
            ->where('audit.findings.data.0.financial_transaction_id', $payment->financial_transaction_id)
            ->where('audit.findings.data.0.entity_label', 'Pedido #'.$paymentOrder->public_id)
            ->where('audit.findings.data.0.amount', 15_000)
            ->where('audit.findings.data.0.status', 'pending')
            ->where('audit.findings.data.0.wallet.id', $this->incomeWallet->id)
            ->where('audit.findings.data.0.age_days', 0)
            ->etc());

        $this->assertDatabaseMissing('management_order_payments', ['order_id' => $legacy->id]);
    }

    public function test_management_transaction_deep_link_returns_the_exact_audited_transaction(): void
    {
        $target = $this->manualMovement(FinancialTransactionType::ManualIncome, 2_000, '2025-01-15');
        $this->manualMovement(FinancialTransactionType::ManualIncome, 3_000, '2026-08-29');

        $this->actingAs($this->admin)->get('/admin/management?transaction_id='.$target->id)
            ->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('filters.transaction_id', $target->id)
            ->where('filters.from', '2025-01-15')
            ->where('filters.to', '2025-01-15')
            ->has('transactions.data', 1)
            ->where('transactions.data.0.id', $target->id));
    }

    public function test_missing_source_id_collision_keeps_the_orphan_transaction_and_wallet_provenance(): void
    {
        $orphanWallet = Wallet::query()->create(['name' => 'Orphan wallet']);
        DB::statement('SET CONSTRAINTS ALL IMMEDIATE');
        // This deliberate corruption exists only inside RefreshDatabase's rollback transaction.
        DB::statement('ALTER TABLE financial_transactions DISABLE TRIGGER financial_transactions_require_order_payment_source');
        DB::statement('SET CONSTRAINTS ALL DEFERRED');
        $orphanTransactionId = (int) DB::table('financial_transactions')->insertGetId([
            'type' => 'order_payment',
            'effective_date' => '2026-08-29',
            'created_by' => $this->admin->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('ledger_entries')->insert([
            'financial_transaction_id' => $orphanTransactionId,
            'wallet_id' => $orphanWallet->id,
            'amount' => 7_000,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::statement(
            "SELECT setval(pg_get_serial_sequence('management_order_payments', 'id'), ?, false)",
            [$orphanTransactionId],
        );

        $this->confirmPayment(8_000);
        $collidingPayment = ManagementOrderPayment::query()->sole();
        $this->assertSame($orphanTransactionId, $collidingPayment->id);
        $this->assertNotSame($orphanTransactionId, $collidingPayment->financial_transaction_id);

        $this->actingAs($this->admin)->get('/admin/management/audit?type=missing_semantic_source&period=30_days')
            ->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('audit.findings.data.0.financial_transaction_id', $orphanTransactionId)
            ->where('audit.findings.data.0.entity_id', $orphanTransactionId)
            ->where('audit.wallet_balances', function ($wallets): bool {
                $statuses = collect($wallets)->mapWithKeys(fn (array $wallet): array => [$wallet['wallet_name'] => $wallet['status']]);

                return $statuses->get('Orphan wallet') === 'critical'
                    && $statuses->get('Caja General') === 'ok';
            }));

        $this->actingAs($this->admin)->get('/admin/management?transaction_id='.$orphanTransactionId)
            ->assertOk()->assertInertia(fn (Assert $page) => $page
            ->has('transactions.data', 1)
            ->where('transactions.data.0.id', $orphanTransactionId));
    }

    public function test_consistent_payment_distribution_transfer_outflow_and_reversal_are_not_critical(): void
    {
        [$operation, $profit, $reserve] = $this->destinationWallets();
        $this->createRule($operation, $profit, $reserve);
        $this->confirmPayment(20_000);
        $this->createTransfer($operation, $profit, 1_000);
        $this->createOutflow($profit, 500);
        $manual = $this->manualMovement(FinancialTransactionType::ManualIncome, 2_000, '2026-08-29');
        app(ReverseFinancialTransaction::class)->execute(
            $manual,
            new ReversalData(CarbonImmutable::today(), 'Correction'),
            $this->admin->id,
        );

        $this->actingAs($this->admin)->get('/admin/management/audit?severity=critical&period=30_days')
            ->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('audit.summary.critical', 0)
            ->has('audit.findings.data', 0));

        $this->actingAs($this->admin)->get('/admin/management/audit?status=ok&period=30_days')
            ->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('audit.summary.ok', 4)
            ->where('audit.summary.critical', 0)
            ->has('audit.findings.data', 0));
    }

    public function test_configuration_gaps_and_reversed_financial_states_are_explained_without_false_criticals(): void
    {
        $this->actingAs($this->admin)->get('/admin/management/audit?type=configuration&period=30_days')
            ->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('audit.summary.warning', 1)
            ->where('audit.findings.data.0.type', 'missing_active_distribution_rule'));

        [$operation, $profit, $reserve] = $this->destinationWallets();
        $this->createRule($operation, $profit, $reserve);
        $this->confirmPayment(20_000);
        $distribution = ManagementDistribution::query()->sole();
        app(ReverseFinancialTransaction::class)->execute(
            $distribution->financialTransaction,
            new ReversalData(CarbonImmutable::today(), 'Reverse distribution'),
            $this->admin->id,
        );
        $payment = ManagementOrderPayment::query()->sole();
        app(ReverseFinancialTransaction::class)->execute(
            $payment->financialTransaction,
            new ReversalData(CarbonImmutable::today(), 'Reverse payment'),
            $this->admin->id,
        );

        $this->actingAs($this->admin)->get('/admin/management/audit?severity=warning&period=30_days')
            ->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('audit.summary.warning', 2)
            ->where('audit.summary.critical', 0)
            ->where('audit.findings.data', fn ($findings): bool => collect($findings)->pluck('type')->sort()->values()->all() === [
                'reversed_distribution', 'reversed_payment',
            ]));
    }

    public function test_payment_amount_wallet_date_and_structure_mismatches_are_derived(): void
    {
        $this->confirmPayment(15_000);
        $payment = ManagementOrderPayment::query()->sole();
        $otherWallet = Wallet::query()->create(['name' => 'Banco']);
        $this->withoutTrigger('management_order_payments', 'management_order_payments_immutable', function () use ($payment, $otherWallet): void {
            DB::table('management_order_payments')->where('id', $payment->id)->update([
                'amount' => 14_000,
                'destination_wallet_id' => $otherWallet->id,
                'effective_date' => '2026-08-28',
            ]);
        });
        $this->withoutTrigger('ledger_entries', 'ledger_entries_immutable', function () use ($payment): void {
            $transactionId = $payment->financial_transaction_id;
            $secondWallet = Wallet::query()->create(['name' => 'Duplicada']);
            DB::table('ledger_entries')->insert([
                'financial_transaction_id' => $transactionId,
                'wallet_id' => $secondWallet->id,
                'amount' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        });

        $this->actingAs($this->admin)->get('/admin/management/audit?severity=critical&type=order_payment&period=30_days')
            ->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('audit.summary.critical', 4)
            ->where('audit.summary.pending', 0)
            ->where('audit.wallet_balances', function ($wallets): bool {
                $statuses = collect($wallets)->mapWithKeys(fn (array $wallet): array => [$wallet['wallet_name'] => $wallet['status']]);

                return $statuses->get('Caja General') === 'critical'
                    && $statuses->get('Banco') === 'critical'
                    && $statuses->get('Duplicada') === 'critical';
            })
            ->has('audit.findings.data', 4)
            ->where('audit.findings.data', fn ($findings) => collect($findings)->pluck('type')->sort()->values()->all() === [
                'amount_mismatch', 'date_mismatch', 'ledger_structure_mismatch', 'wallet_mismatch',
            ]));

        $this->actingAs($this->admin)->get('/admin/management/audit?type=pending_distribution&period=30_days')
            ->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('audit.summary.pending', 0)
            ->has('audit.findings.data', 0));
    }

    public function test_transfer_outflow_distribution_and_reversal_imbalances_are_critical(): void
    {
        [$operation, $profit, $reserve] = $this->destinationWallets();
        $this->createRule($operation, $profit, $reserve);
        $this->confirmPayment(20_000);
        $transfer = $this->createTransfer($operation, $profit, 1_000);
        $outflow = $this->createOutflow($profit, 500);
        $manual = $this->manualMovement(FinancialTransactionType::ManualIncome, 2_000, '2026-08-29');
        $reversal = app(ReverseFinancialTransaction::class)->execute(
            $manual,
            new ReversalData(CarbonImmutable::today(), 'Correction'),
            $this->admin->id,
        );

        $this->withoutTrigger('ledger_entries', 'ledger_entries_immutable', function () use ($transfer, $outflow, $reversal): void {
            DB::table('ledger_entries')->where('financial_transaction_id', $transfer->financial_transaction_id)
                ->where('amount', '>', 0)->update(['amount' => 999]);
            DB::table('ledger_entries')->where('financial_transaction_id', $outflow->financial_transaction_id)
                ->update(['amount' => -499]);
            DB::table('ledger_entries')->where('financial_transaction_id', $reversal->id)
                ->update(['amount' => -1_999]);
            $distributionId = DB::table('management_distributions')->value('financial_transaction_id');
            DB::table('ledger_entries')->where('financial_transaction_id', $distributionId)
                ->where('amount', '>', 0)->limit(1)->update(['amount' => DB::raw('amount - 1')]);
        });

        $this->actingAs($this->admin)->get('/admin/management/audit?severity=critical&period=30_days')
            ->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('audit.summary.critical', fn (int $count): bool => $count >= 4)
            ->where('audit.findings.data', function ($findings): bool {
                $entities = collect($findings)->pluck('entity_type');

                return collect(['transfer', 'management_outflow', 'distribution', 'reversal'])->every(fn (string $type): bool => $entities->contains($type));
            }));
    }

    public function test_filters_are_bounded_and_query_count_does_not_grow_per_payment(): void
    {
        $first = $this->confirmPayment(1_000);
        $baseline = $this->requestQueryCount('/admin/management/audit?period=custom&from=2026-08-29&to=2026-08-29&type=pending_distribution&severity=info&status=pending&wallet_id='.$this->incomeWallet->id);
        for ($index = 0; $index < 12; $index++) {
            $this->confirmPayment(1_001 + $index);
        }

        $url = '/admin/management/audit?period=custom&from=2026-08-29&to=2026-08-29&type=pending_distribution&severity=info&status=pending&wallet_id='.$this->incomeWallet->id;
        $this->assertSame($baseline, $this->requestQueryCount($url));
        $this->actingAs($this->admin)->get($url)->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('audit.filters.period', 'custom')
            ->where('audit.filters.from', '2026-08-29')
            ->where('audit.filters.to', '2026-08-29')
            ->where('audit.filters.wallet_id', $this->incomeWallet->id)
            ->where('audit.summary.pending', 13)
            ->where('audit.findings.total', 13));
        $this->assertNotNull($first->id);
    }

    private function confirmPayment(int $total): Order
    {
        $order = $this->order($total);
        app(UpdateOrderPaymentStatus::class)->execute(
            $order,
            PaymentStatus::Paid,
            PaymentMethod::Cash,
            $this->admin->id,
        );

        return $order->refresh();
    }

    private function order(int $total, string $paymentStatus = 'unpaid'): Order
    {
        $customer = Customer::query()->create(['name' => 'Audit customer']);

        return Order::query()->create([
            'customer_id' => $customer->id,
            'source' => 'admin',
            'customer_name_snapshot' => $customer->name,
            'status' => 'waiting',
            'payment_status' => $paymentStatus,
            'payment_method' => $paymentStatus === 'paid' ? 'cash' : null,
            'condition' => 'raw',
            'delivery_type' => 'pickup',
            'delivery_period' => 'night',
            'delivery_date' => '2026-08-29',
            'items_cooked_subtotal' => $total,
            'raw_discount' => 0,
            'items_subtotal' => $total,
            'toppings_subtotal' => 0,
            'shipping_fee_snapshot' => 0,
            'combined_dozen_savings' => 0,
            'discount_amount' => 0,
            'total' => $total,
        ])->refresh();
    }

    /** @return list<Wallet> */
    private function destinationWallets(): array
    {
        return collect(['Operación', 'Ganancia', 'Reserva'])
            ->map(fn (string $name): Wallet => Wallet::query()->create(['name' => $name]))
            ->all();
    }

    private function createRule(Wallet $operation, Wallet $profit, Wallet $reserve): void
    {
        app(CreateDistributionRule::class)->execute(new DistributionRuleData('Distribución', [
            new DistributionAllocationData($operation->id, 5000, 0),
            new DistributionAllocationData($profit->id, 3000, 1),
            new DistributionAllocationData($reserve->id, 2000, 2),
        ]), $this->admin->id);
    }

    private function createTransfer(Wallet $source, Wallet $destination, int $amount): ManagementTransfer
    {
        return app(CreateManagementTransfer::class)->execute(new ManagementTransferData(
            $source->id, $destination->id, $amount, CarbonImmutable::today(), null,
        ), $this->admin->id);
    }

    private function createOutflow(Wallet $wallet, int $amount): ManagementOutflow
    {
        $category = ManagementOutflowCategory::query()->where('type', ManagementOutflowType::Expense)->firstOrFail();

        return app(CreateManagementOutflow::class)->execute(new ManagementOutflowData(
            ManagementOutflowType::Expense,
            $category->id,
            $wallet->id,
            'Audit outflow',
            null,
            $amount,
            CarbonImmutable::today(),
        ), $this->admin->id);
    }

    private function manualMovement(FinancialTransactionType $type, int $amount, string $date): FinancialTransaction
    {
        return app(CreateManualTransaction::class)->execute(new ManualTransactionData(
            $this->incomeWallet->id, $type, $amount, CarbonImmutable::parse($date), 'Audit movement',
        ), $this->admin->id);
    }

    private function withoutTrigger(string $table, string $trigger, Closure $mutation): void
    {
        DB::statement('SET CONSTRAINTS ALL IMMEDIATE');
        DB::statement("ALTER TABLE {$table} DISABLE TRIGGER {$trigger}");
        try {
            $mutation();
            DB::statement('SET CONSTRAINTS ALL IMMEDIATE');
        } finally {
            DB::statement("ALTER TABLE {$table} ENABLE TRIGGER {$trigger}");
            DB::statement('SET CONSTRAINTS ALL DEFERRED');
        }
    }

    /** @return array{int, int, string} */
    private function financialSnapshot(): array
    {
        return [
            DB::table('financial_transactions')->count(),
            DB::table('ledger_entries')->count(),
            (string) DB::table('ledger_entries')->sum('amount'),
        ];
    }

    private function requestQueryCount(string $url): int
    {
        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->actingAs($this->admin)->get($url)->assertOk();
        $count = count(DB::getQueryLog());
        DB::disableQueryLog();

        return $count;
    }
}
