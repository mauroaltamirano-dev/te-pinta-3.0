<?php

namespace Tests\Feature\Management;

use App\Modules\Customers\Models\Customer;
use App\Modules\Identity\Models\User;
use App\Modules\Management\Actions\CreateDistributionRule;
use App\Modules\Management\Actions\CreateDistributionRuleVersion;
use App\Modules\Management\Actions\ReverseFinancialTransaction;
use App\Modules\Management\Data\DistributionAllocationData;
use App\Modules\Management\Data\DistributionRuleData;
use App\Modules\Management\Data\ReversalData;
use App\Modules\Management\Enums\FinancialTransactionType;
use App\Modules\Management\Models\DistributionRule;
use App\Modules\Management\Models\FinancialTransaction;
use App\Modules\Management\Models\LedgerEntry;
use App\Modules\Management\Models\ManagementDistribution;
use App\Modules\Management\Models\ManagementOrderPayment;
use App\Modules\Management\Models\Wallet;
use App\Modules\Management\Queries\BuildManagementOverview;
use App\Modules\Orders\Actions\UpdateOrderPaymentStatus;
use App\Modules\Orders\Enums\PaymentMethod;
use App\Modules\Orders\Enums\PaymentStatus;
use App\Modules\Orders\Events\OrderPaymentConfirmed;
use App\Modules\Orders\Models\Order;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\Event;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;
use RuntimeException;
use Tests\TestCase;

final class ManagementOrderPaymentTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Wallet $wallet;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        CarbonImmutable::setTestNow('2026-08-28 12:00:00');
        $this->admin = User::factory()->administrator()->create();
        $this->wallet = Wallet::query()->create([
            'name' => 'Caja',
            'is_order_payment_default' => true,
        ]);
    }

    public function test_paid_transition_creates_one_structured_income_and_repeated_confirmation_is_idempotent(): void
    {
        $order = $this->order(15_000);

        app(UpdateOrderPaymentStatus::class)->execute(
            $order,
            PaymentStatus::Paid,
            PaymentMethod::Cash,
            $this->admin->id,
        );
        event(new OrderPaymentConfirmed($order->id, 15_000, CarbonImmutable::today(), $this->admin->id));

        $payment = ManagementOrderPayment::query()->sole();
        $transaction = FinancialTransaction::query()->sole();
        $entry = LedgerEntry::query()->sole();
        $this->assertSame($order->id, $payment->order_id);
        $this->assertSame($this->wallet->id, $payment->destination_wallet_id);
        $this->assertSame(15_000, (int) $payment->amount);
        $this->assertSame('2026-08-28', $payment->effective_date->toDateString());
        $this->assertSame(FinancialTransactionType::OrderPayment, $transaction->type);
        $this->assertSame($transaction->id, $payment->financial_transaction_id);
        $this->assertSame($this->wallet->id, $entry->wallet_id);
        $this->assertSame(15_000, (int) $entry->amount);
        $this->assertDatabaseCount('management_order_payments', 1);
        $this->assertDatabaseCount('financial_transactions', 1);
        $this->assertDatabaseCount('ledger_entries', 1);
    }

    public function test_paid_transition_automatically_applies_the_active_rule_conservatively_once(): void
    {
        $destinations = $this->destinationWallets();
        $rule = $this->createRule($destinations);
        $order = $this->order(10_001);

        app(UpdateOrderPaymentStatus::class)->execute(
            $order,
            PaymentStatus::Paid,
            PaymentMethod::Cash,
            $this->admin->id,
        );
        event(new OrderPaymentConfirmed($order->id, 10_001, CarbonImmutable::today(), $this->admin->id));

        $payment = ManagementOrderPayment::query()->with('distribution.allocations')->sole();
        $distribution = $payment->distribution;
        $this->assertInstanceOf(ManagementDistribution::class, $distribution);
        $this->assertSame($this->wallet->id, $distribution->source_wallet_id);
        $this->assertSame(10_001, (int) $distribution->amount);
        $this->assertSame($payment->effective_date->toDateString(), $distribution->effective_date->toDateString());
        $this->assertSame($rule->versions()->sole()->id, $distribution->rule_version_id);
        $this->assertSame([5000, 3000, 2001], $distribution->allocations->pluck('amount')->map(fn ($amount): int => (int) $amount)->all());

        $distributionEntries = $distribution->financialTransaction->entries()->orderBy('id')->pluck('amount')->map(fn ($amount): int => (int) $amount);
        $this->assertSame([-10_001, 5000, 3000, 2001], $distributionEntries->all());
        $this->assertSame(0, $distributionEntries->sum());
        $this->assertDatabaseCount('management_order_payments', 1);
        $this->assertDatabaseCount('management_distributions', 1);
        $this->assertDatabaseCount('financial_transactions', 2);
        $this->assertDatabaseCount('ledger_entries', 5);

        $balances = Wallet::query()->withSum('ledgerEntries as balance', 'amount')->get()->keyBy('id');
        $this->assertSame(0, (int) $balances[$this->wallet->id]->balance);
        $this->assertSame([5000, 3000, 2001], collect($destinations)->map(fn (Wallet $wallet): int => (int) $balances[$wallet->id]->balance)->all());

        $summary = app(BuildManagementOverview::class)->execute([
            'wallet_id' => null, 'type' => null, 'category_id' => null,
            'from' => '2026-08-01', 'to' => '2026-08-31',
        ])['summary'];
        $this->assertSame(10_001, $summary['period_income']);
        $this->assertSame(10_001, $summary['period_distribution']);
        $this->assertSame(0, $summary['period_expense']);
        $this->assertSame(0, $summary['period_operating_outflow']);
        $this->assertSame(0, $summary['period_personal_withdrawal']);
        $this->assertSame(10_001, $summary['total_balance']);

        $this->actingAs($this->admin)
            ->get('/admin/management?from=2026-08-01&to=2026-08-31')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('transactions.data', 2)
                ->where('transactions.data.0.type', 'distribution')
                ->where('transactions.data.0.management_distribution.management_order_payment.id', $payment->id)
                ->where('transactions.data.0.management_distribution.management_order_payment.order.public_id', $order->public_id)
                ->where('transactions.data.1.type', 'order_payment')
                ->where('transactions.data.1.management_order_payment.distribution_status', 'distributed')
                ->where('transactions.data.1.management_order_payment.distribution.id', $distribution->id));
    }

    public function test_payment_without_active_rule_is_visible_as_pending_and_can_be_retried_by_another_admin(): void
    {
        $order = $this->order(10_000);
        app(UpdateOrderPaymentStatus::class)->execute($order, PaymentStatus::Paid, PaymentMethod::Cash, $this->admin->id);
        $payment = ManagementOrderPayment::query()->sole();

        $this->post("/admin/management/order-payments/{$payment->id}/distribution")->assertRedirect('/login');
        $this->actingAs(User::factory()->create())
            ->post("/admin/management/order-payments/{$payment->id}/distribution")
            ->assertForbidden();

        $this->actingAs($this->admin)
            ->get('/admin/management?from=2026-08-01&to=2026-08-31')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('transactions.data.0.management_order_payment.distribution_status', 'pending')
                ->where('transactions.data.0.management_order_payment.distribution', null));

        $retryingAdmin = User::factory()->administrator()->create();
        $rule = $this->createRule($this->destinationWallets(), admin: $retryingAdmin);
        $this->actingAs($retryingAdmin)
            ->post("/admin/management/order-payments/{$payment->id}/distribution")
            ->assertRedirect();

        $distribution = ManagementDistribution::query()->sole();
        $this->assertSame($payment->id, $distribution->management_order_payment_id);
        $this->assertSame($retryingAdmin->id, $distribution->created_by);
        $this->assertSame($rule->versions()->sole()->id, $distribution->rule_version_id);
    }

    public function test_automatic_distribution_preserves_the_selected_version_and_a_later_payment_uses_the_new_version(): void
    {
        $destinations = $this->destinationWallets();
        $rule = $this->createRule($destinations);
        $first = $this->order(10_000);
        app(UpdateOrderPaymentStatus::class)->execute($first, PaymentStatus::Paid, PaymentMethod::Cash, $this->admin->id);
        $v1 = ManagementDistribution::query()->sole()->rule_version_id;

        $v2 = app(CreateDistributionRuleVersion::class)->execute($rule, [
            new DistributionAllocationData($destinations[0]->id, 6000, 0),
            new DistributionAllocationData($destinations[1]->id, 2500, 1),
            new DistributionAllocationData($destinations[2]->id, 1500, 2),
        ], $this->admin->id);
        $second = $this->order(10_000);
        app(UpdateOrderPaymentStatus::class)->execute($second, PaymentStatus::Paid, PaymentMethod::Cash, $this->admin->id);

        $distributions = ManagementDistribution::query()->orderBy('id')->get();
        $this->assertSame($v1, $distributions[0]->rule_version_id);
        $this->assertSame($v2->id, $distributions[1]->rule_version_id);
        $this->assertSame([5000, 3000, 2000], $distributions[0]->allocations()->pluck('amount')->map(fn ($amount): int => (int) $amount)->all());
        $this->assertSame([6000, 2500, 1500], $distributions[1]->allocations()->pluck('amount')->map(fn ($amount): int => (int) $amount)->all());
    }

    public function test_payment_reversal_requires_reversing_its_distribution_first(): void
    {
        $destinations = $this->destinationWallets();
        $this->createRule($destinations);
        $order = $this->order(10_000);
        app(UpdateOrderPaymentStatus::class)->execute($order, PaymentStatus::Paid, PaymentMethod::Cash, $this->admin->id);
        $payment = ManagementOrderPayment::query()->with('distribution')->sole();

        try {
            app(ReverseFinancialTransaction::class)->execute(
                $payment->financialTransaction,
                new ReversalData(CarbonImmutable::parse('2026-08-29'), 'Payment correction'),
                $this->admin->id,
            );
            $this->fail('Expected the payment reversal ordering invariant to fail.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('transaction', $exception->errors());
        }

        $replacement = Wallet::query()->create(['name' => 'Banco']);
        $this->wallet->update(['is_order_payment_default' => false]);
        $replacement->update(['is_order_payment_default' => true]);
        $this->wallet->update(['archived_at' => now()]);
        foreach ($destinations as $destination) {
            $destination->update(['archived_at' => now()]);
        }

        app(ReverseFinancialTransaction::class)->execute(
            $payment->distribution->financialTransaction,
            new ReversalData(CarbonImmutable::parse('2026-08-29'), 'Distribution correction'),
            $this->admin->id,
        );
        app(ReverseFinancialTransaction::class)->execute(
            $payment->financialTransaction,
            new ReversalData(CarbonImmutable::parse('2026-08-29'), 'Payment correction'),
            $this->admin->id,
        );

        $this->assertSame(0, (int) LedgerEntry::query()->sum('amount'));
        $this->assertDatabaseCount('management_order_payments', 1);
        $this->assertDatabaseCount('management_distributions', 1);
        $this->assertDatabaseCount('financial_transactions', 4);
    }

    public function test_a_reverted_pending_payment_cannot_be_distributed_by_a_later_retry(): void
    {
        $order = $this->order(10_000);
        app(UpdateOrderPaymentStatus::class)->execute($order, PaymentStatus::Paid, PaymentMethod::Cash, $this->admin->id);
        $payment = ManagementOrderPayment::query()->sole();
        app(ReverseFinancialTransaction::class)->execute(
            $payment->financialTransaction,
            new ReversalData(CarbonImmutable::parse('2026-08-29'), 'Payment correction'),
            $this->admin->id,
        );
        $this->createRule($this->destinationWallets());

        $this->actingAs($this->admin)
            ->post("/admin/management/order-payments/{$payment->id}/distribution")
            ->assertSessionHasErrors('transaction');

        $this->assertDatabaseCount('management_distributions', 0);
        $this->assertDatabaseCount('financial_transactions', 2);
        $this->assertDatabaseCount('ledger_entries', 2);
        $this->assertSame(0, (int) LedgerEntry::query()->sum('amount'));
    }

    public function test_distribution_failure_rolls_back_order_payment_and_paid_transition(): void
    {
        $this->createRule($this->destinationWallets());
        $order = $this->order(10_000);
        $eloquentEvent = 'eloquent.creating: '.LedgerEntry::class;
        Event::listen($eloquentEvent, static function (): void {
            if (FinancialTransaction::query()->where('type', FinancialTransactionType::Distribution)->exists()) {
                throw new RuntimeException('Simulated distribution ledger failure.');
            }
        });

        try {
            app(UpdateOrderPaymentStatus::class)->execute($order, PaymentStatus::Paid, PaymentMethod::Cash, $this->admin->id);
            $this->fail('Expected automatic distribution to fail.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Simulated distribution ledger failure.', $exception->getMessage());
        } finally {
            Event::forget($eloquentEvent);
        }

        $this->assertSame(PaymentStatus::Unpaid, $order->fresh()->payment_status);
        $this->assertDatabaseCount('management_order_payments', 0);
        $this->assertDatabaseCount('management_distributions', 0);
        $this->assertDatabaseCount('financial_transactions', 0);
        $this->assertDatabaseCount('ledger_entries', 0);
    }

    public function test_unpaid_order_does_not_create_financial_history(): void
    {
        $order = $this->order(15_000);

        app(UpdateOrderPaymentStatus::class)->execute(
            $order,
            PaymentStatus::Unpaid,
            null,
            $this->admin->id,
        );

        $this->assertDatabaseCount('management_order_payments', 0);
        $this->assertDatabaseCount('financial_transactions', 0);
        $this->assertDatabaseCount('ledger_entries', 0);
    }

    public function test_direct_confirmation_rejects_an_order_that_is_not_currently_paid(): void
    {
        $order = $this->order(15_000);

        try {
            event(new OrderPaymentConfirmed($order->id, 15_000, CarbonImmutable::today(), $this->admin->id));
            $this->fail('Expected an unpaid order confirmation to fail.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('payment_status', $exception->errors());
        }

        $this->assertDatabaseCount('management_order_payments', 0);
        $this->assertDatabaseCount('financial_transactions', 0);
        $this->assertDatabaseCount('ledger_entries', 0);
    }

    public function test_effective_date_uses_the_configured_orders_business_timezone(): void
    {
        config()->set('orders.timezone', 'America/Argentina/Buenos_Aires');
        CarbonImmutable::setTestNow('2026-08-29 02:30:00 UTC');
        $order = $this->order(15_000);

        app(UpdateOrderPaymentStatus::class)->execute(
            $order,
            PaymentStatus::Paid,
            PaymentMethod::Cash,
            $this->admin->id,
        );

        $this->assertSame('2026-08-28', ManagementOrderPayment::query()->sole()->effective_date->toDateString());
    }

    public function test_reconfirmation_with_a_different_amount_fails_without_mutating_the_original(): void
    {
        $order = $this->order(15_000);
        app(UpdateOrderPaymentStatus::class)->execute($order, PaymentStatus::Paid, PaymentMethod::Cash, $this->admin->id);

        try {
            event(new OrderPaymentConfirmed($order->id, 16_000, CarbonImmutable::today(), $this->admin->id));
            $this->fail('Expected the incompatible confirmation to fail.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('payment_status', $exception->errors());
        }

        $this->assertSame(15_000, (int) ManagementOrderPayment::query()->sole()->amount);
        $this->assertSame(15_000, (int) LedgerEntry::query()->sole()->amount);
        $this->assertDatabaseCount('management_order_payments', 1);
        $this->assertDatabaseCount('financial_transactions', 1);
    }

    public function test_missing_default_wallet_rolls_back_the_paid_transition(): void
    {
        $order = $this->order(15_000);
        $this->wallet->update(['is_order_payment_default' => false]);

        try {
            app(UpdateOrderPaymentStatus::class)->execute(
                $order,
                PaymentStatus::Paid,
                PaymentMethod::Cash,
                $this->admin->id,
            );
            $this->fail('Expected confirmation without a destination wallet to fail.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('payment_status', $exception->errors());
        }

        $this->assertSame(PaymentStatus::Unpaid, $order->fresh()->payment_status);
        $this->assertDatabaseCount('management_order_payments', 0);
        $this->assertDatabaseCount('financial_transactions', 0);
        $this->assertDatabaseCount('ledger_entries', 0);
    }

    public function test_order_payment_increments_only_income_and_has_semantic_history_and_filter(): void
    {
        $order = $this->order(15_000);
        app(UpdateOrderPaymentStatus::class)->execute($order, PaymentStatus::Paid, PaymentMethod::Cash, $this->admin->id);

        $this->actingAs($this->admin)
            ->get('/admin/management?type=order_payment&from=2026-08-01&to=2026-08-31')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('summary.period_income', 15_000)
                ->where('summary.period_expense', 0)
                ->where('summary.period_operating_outflow', 0)
                ->where('summary.period_personal_withdrawal', 0)
                ->where('summary.period_distribution', 0)
                ->where('summary.total_balance', 15_000)
                ->has('transactions.data', 1)
                ->where('transactions.data.0.type', 'order_payment')
                ->where('transactions.data.0.management_order_payment.order.public_id', $order->public_id)
                ->where('transactions.data.0.management_order_payment.order.customer_name', 'Order customer')
                ->where('transactions.data.0.management_order_payment.destination_wallet.id', $this->wallet->id)
                ->where('transactions.data.0.management_order_payment.amount', 15_000));
    }

    public function test_reversal_preserves_source_and_inverts_income_even_after_destination_is_archived(): void
    {
        $order = $this->order(15_000);
        app(UpdateOrderPaymentStatus::class)->execute($order, PaymentStatus::Paid, PaymentMethod::Cash, $this->admin->id);
        $original = FinancialTransaction::query()->sole();
        $replacement = Wallet::query()->create(['name' => 'Banco', 'is_order_payment_default' => false]);
        $this->wallet->update(['is_order_payment_default' => false]);
        $replacement->update(['is_order_payment_default' => true]);
        $this->wallet->update(['archived_at' => now()]);

        app(ReverseFinancialTransaction::class)->execute(
            $original,
            new ReversalData(CarbonImmutable::parse('2026-08-29'), 'Order payment correction'),
            $this->admin->id,
        );

        $reversal = FinancialTransaction::query()->where('reversal_of_id', $original->id)->sole();
        $this->assertSame(-15_000, (int) $reversal->entries()->value('amount'));
        $this->assertSame(15_000, (int) $original->entries()->value('amount'));
        $this->assertDatabaseCount('management_order_payments', 1);
        $summary = app(BuildManagementOverview::class)->execute([
            'wallet_id' => null,
            'type' => null,
            'category_id' => null,
            'from' => '2026-08-01',
            'to' => '2026-08-31',
        ])['summary'];
        $this->assertSame(0, $summary['period_income']);
    }

    public function test_order_payment_creation_rolls_back_completely_when_entry_creation_fails(): void
    {
        $order = $this->order(15_000);
        $eloquentEvent = 'eloquent.creating: '.LedgerEntry::class;
        Event::listen($eloquentEvent, static function (): never {
            throw new RuntimeException('Simulated ledger failure.');
        });

        try {
            app(UpdateOrderPaymentStatus::class)->execute($order, PaymentStatus::Paid, PaymentMethod::Cash, $this->admin->id);
            $this->fail('Expected the ledger entry to fail.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Simulated ledger failure.', $exception->getMessage());
        } finally {
            Event::forget($eloquentEvent);
        }

        $this->assertSame(PaymentStatus::Unpaid, $order->fresh()->payment_status);
        $this->assertDatabaseCount('management_order_payments', 0);
        $this->assertDatabaseCount('financial_transactions', 0);
        $this->assertDatabaseCount('ledger_entries', 0);
    }

    public function test_order_payment_logs_only_structured_identifiers(): void
    {
        $order = $this->order(15_000);
        Event::fake([MessageLogged::class]);

        app(UpdateOrderPaymentStatus::class)->execute($order, PaymentStatus::Paid, PaymentMethod::Cash, $this->admin->id);

        Event::assertDispatched(MessageLogged::class, fn (MessageLogged $event): bool => $event->message === '[Management] order_payment.created'
            && $event->context['order_id'] === $order->id
            && $event->context['wallet_id'] === $this->wallet->id
            && $event->context['admin_id'] === $this->admin->id
            && ! array_key_exists('customer', $event->context)
            && ! array_key_exists('payload', $event->context));
    }

    private function order(int $total): Order
    {
        $customer = Customer::query()->create(['name' => 'Order customer']);

        return Order::query()->create([
            'customer_id' => $customer->id,
            'source' => 'admin',
            'customer_name_snapshot' => $customer->name,
            'status' => 'waiting',
            'payment_status' => 'unpaid',
            'payment_method' => null,
            'condition' => 'raw',
            'delivery_type' => 'pickup',
            'delivery_period' => 'night',
            'delivery_date' => '2026-08-28',
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

    /** @param list<Wallet> $wallets */
    private function createRule(array $wallets, ?User $admin = null): DistributionRule
    {
        $admin ??= $this->admin;

        return app(CreateDistributionRule::class)->execute(new DistributionRuleData('Distribución negocio', [
            new DistributionAllocationData($wallets[0]->id, 5000, 0),
            new DistributionAllocationData($wallets[1]->id, 3000, 1),
            new DistributionAllocationData($wallets[2]->id, 2000, 2),
        ]), $admin->id);
    }
}
