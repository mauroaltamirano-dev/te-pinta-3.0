<?php

namespace Tests\Feature\Management;

use App\Modules\Customers\Models\Customer;
use App\Modules\Identity\Enums\Role;
use App\Modules\Identity\Models\User;
use App\Modules\Management\Models\DistributionRuleVersion;
use App\Modules\Management\Models\FinancialTransaction;
use App\Modules\Management\Models\LedgerEntry;
use App\Modules\Management\Models\ManagementDistribution;
use App\Modules\Management\Models\ManagementOutflowCategory;
use App\Modules\Management\Models\Purchase;
use App\Modules\Management\Models\Wallet;
use App\Modules\Orders\Models\Order;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\CostingFixtures;
use Tests\TestCase;

final class OperationalManagementTest extends TestCase
{
    use CostingFixtures, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        CarbonImmutable::setTestNow('2026-08-26 01:30:00 UTC');
        $this->costingFixture();
        $this->actingAs($this->admin);
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    public function test_defaults_use_local_today_and_separate_summary_and_history_windows(): void
    {
        $this->get('/admin/management')->assertOk()->assertInertia(fn (Assert $p) => $p
            ->where('business_today', '2026-08-25')
            ->where('filters.period', '30_days')->where('filters.from', '2026-07-27')->where('filters.to', '2026-08-25')
            ->where('filters.history_period', '7_days')->where('filters.history_from', '2026-08-19')->where('filters.history_to', '2026-08-25'));
    }

    #[DataProvider('periods')]
    public function test_named_periods_preserve_business_dates(string $period, string $from, string $to): void
    {
        $this->get('/admin/management?period='.$period)->assertOk()->assertInertia(fn (Assert $p) => $p
            ->where('filters.from', $from)->where('filters.to', $to));
    }

    public static function periods(): array
    {
        return [['today', '2026-08-25', '2026-08-25'], ['7_days', '2026-08-19', '2026-08-25'], ['30_days', '2026-07-27', '2026-08-25'], ['this_month', '2026-08-01', '2026-08-25'], ['previous_month', '2026-07-01', '2026-07-31'], ['week', '2026-08-19', '2026-08-25'], ['month', '2026-07-27', '2026-08-25']];
    }

    #[DataProvider('invalidPeriods')]
    public function test_invalid_periods_fail_without_creating_financial_records(string $query, string $error): void
    {
        $before = [FinancialTransaction::count(), LedgerEntry::count()];
        $this->get('/admin/management?'.$query)->assertSessionHasErrors($error);
        $this->assertSame($before, [FinancialTransaction::count(), LedgerEntry::count()]);
    }

    public static function invalidPeriods(): array
    {
        return [['period=invalid', 'period'], ['history_period=invalid', 'history_period'], ['period=custom&from=2026-08-26&to=2026-08-25', 'to'], ['history_period=custom&history_from=2026-08-26&history_to=2026-08-25', 'history_to'], ['from=not-a-date', 'from']];
    }

    public function test_custom_reporting_and_history_ranges_are_independent_and_filters_do_not_change_totals(): void
    {
        $wallet = Wallet::create(['name' => 'Cash']);
        $this->movement($wallet, 'manual_income', 1000, '2026-08-01');
        $this->movement($wallet, 'manual_income', 2000, '2026-08-20');
        $this->get('/admin/management?period=custom&from=2026-08-01&to=2026-08-25&history_period=custom&history_from=2026-08-19&history_to=2026-08-25&type=manual_income')
            ->assertOk()->assertInertia(fn (Assert $p) => $p->where('summary.period_income', 3000)->has('transactions.data', 1)->where('transactions.data.0.effective_date', '2026-08-20'));
    }

    public function test_ledger_balances_and_operating_result_include_archived_history_and_signed_reversals(): void
    {
        $wallet = Wallet::create(['name' => 'Historic wallet']);
        $this->movement($wallet, 'opening', 10000, '2026-08-01');
        $income = $this->movement($wallet, 'manual_income', 5000, '2026-08-20');
        $expense = $this->movement($wallet, 'manual_expense', 1000, '2026-08-21');
        $this->movement($wallet, 'adjustment_increase', 250, '2026-08-22');
        $this->movement($wallet, 'manual_expense', 600, '2026-08-26');
        $this->post('/admin/management/transactions/'.$expense->id.'/reverse', ['effective_date' => '2026-08-23'])->assertRedirect()->assertSessionHasNoErrors();
        $this->post('/admin/management/wallets/'.$wallet->id.'/archive')->assertRedirect();
        $this->get('/admin/management?from=2026-08-19&to=2026-08-25')->assertOk()->assertInertia(fn (Assert $p) => $p
            ->where('summary.total_balance', 14650)->where('summary.period_opening_balance', 10000)->where('summary.period_closing_balance', 15250)
            ->where('summary.period_income', 5000)->where('summary.period_expense', 0)->where('summary.period_operating_result', 5000)
            ->where('summary.period_balance_change', 5250)->where('summary.period_other_movements', 250)->where('summary.reconciliation_difference', 0)
            ->where('wallets.0.balance', 14650));
        $this->post('/admin/management/transactions/'.$income->id.'/reverse', ['effective_date' => '2026-08-24'])->assertRedirect()->assertSessionHasNoErrors();
        $this->get('/admin/management?from=2026-08-19&to=2026-08-25')->assertInertia(fn (Assert $p) => $p->where('summary.period_income', 0)->where('summary.period_operating_result', 0)->where('summary.reconciliation_difference', 0));
    }

    #[DataProvider('stockModes')]
    public function test_rich_and_legacy_purchases_share_ledger_metrics_without_double_counting(bool $stockable): void
    {
        $wallet = Wallet::create(['name' => 'Purchases']);
        if (! $stockable) {
            $this->box->update(['tracks_stock' => false]);
        }
        $this->movement($wallet, 'manual_income', 5000, '2026-08-20');
        foreach (['purchase' => 500, 'expense' => 200, 'personal_withdrawal' => 300] as $type => $amount) {
            $category = ManagementOutflowCategory::where('type', $type)->firstOrFail();
            $this->post('/admin/management/outflows', ['wallet_id' => $wallet->id, 'type' => $type, 'category_id' => $category->id, 'amount' => $amount, 'description' => 'Historical operation', 'effective_date' => '2026-08-20'])->assertRedirect()->assertSessionHasNoErrors();
        }
        $this->post('/admin/management/purchases', ['idempotency_key' => (string) Str::uuid(), 'wallet_id' => $wallet->id, 'effective_date' => '2026-08-21', 'shipping_amount' => 150, 'lines' => [['inventory_item_id' => $this->box->id, 'input_unit_id' => $this->unit->id, 'quantity' => '2', 'price_mode' => 'total', 'price_amount' => 1000]]])->assertRedirect()->assertSessionHasNoErrors();
        $purchase = Purchase::sole();
        $this->get('/admin/management?from=2026-08-19&to=2026-08-25&type=purchase')->assertOk()->assertInertia(fn (Assert $p) => $p
            ->where('summary.period_purchases', 1650)->where('summary.period_purchase_shipping', 150)->where('summary.period_purchase_subtotal', 1500)
            ->where('summary.period_expenses', 200)->where('summary.period_personal_withdrawal', 300)->where('summary.period_operating_result', 3150)
            ->where('summary.reconciliation_difference', 0)->has('transactions.data', 2)->where('transactions.data.0.purchase_id', $purchase->id));
        $this->post('/admin/management/purchases/'.$purchase->id.'/reverse', ['effective_date' => '2026-08-22', 'description' => 'Incorrect receipt'])->assertRedirect()->assertSessionHasNoErrors();
        $this->get('/admin/management?from=2026-08-19&to=2026-08-25&type=purchase')->assertInertia(fn (Assert $p) => $p
            ->where('summary.period_purchases', 500)->where('summary.period_purchase_shipping', 0)->where('summary.period_operating_result', 4300)
            ->has('transactions.data', 3)->where('transactions.data.0.purchase_id', $purchase->id));
        $this->assertDatabaseCount('inventory_stock_movements', $stockable ? 2 : 0);
    }

    public static function stockModes(): array
    {
        return [[true], [false]];
    }

    public function test_transfers_are_neutral_in_operating_income_and_reconciliation(): void
    {
        $source = Wallet::create(['name' => 'Source']);
        $destination = Wallet::create(['name' => 'Destination']);
        $this->movement($source, 'opening', 2000, '2026-08-01');
        $this->post('/admin/management/transfers', ['source_wallet_id' => $source->id, 'destination_wallet_id' => $destination->id, 'amount' => 1000, 'effective_date' => '2026-08-20'])->assertRedirect()->assertSessionHasNoErrors();
        $this->get('/admin/management?from=2026-08-19&to=2026-08-25')->assertInertia(fn (Assert $p) => $p
            ->where('summary.total_balance', 2000)->where('summary.period_opening_balance', 2000)->where('summary.period_closing_balance', 2000)
            ->where('summary.period_income', 0)->where('summary.period_operating_result', 0)->where('summary.reconciliation_difference', 0));
    }

    public function test_transaction_deep_link_selects_its_business_date_and_is_read_only(): void
    {
        $wallet = Wallet::create(['name' => 'Linked']);
        $transaction = $this->movement($wallet, 'manual_income', 123, '2026-06-10');
        $before = [FinancialTransaction::count(), LedgerEntry::count()];
        $this->get('/admin/management?transaction_id='.$transaction->id)->assertOk()->assertInertia(fn (Assert $p) => $p->where('filters.history_from', '2026-06-10')->where('filters.history_to', '2026-06-10')->has('transactions.data', 1));
        $this->assertSame($before, [FinancialTransaction::count(), LedgerEntry::count()]);
    }

    public function test_management_reads_reject_fixed_staff_roles(): void
    {
        foreach (['waiter', 'cashier'] as $role) {
            $user = User::factory()->withRole(Role::from($role))->create();
            foreach (['/admin/management', '/admin/management?section=wallets', '/admin/management/audit', '/admin/management/purchases'] as $url) {
                $this->actingAs($user)->get($url)->assertForbidden();
            }
        }
    }

    public function test_audit_defaults_use_the_same_business_day_as_management(): void
    {
        $this->get('/admin/management/audit?period=today')->assertOk()->assertInertia(fn (Assert $p) => $p
            ->where('audit.filters.from', '2026-08-25')->where('audit.filters.to', '2026-08-25'));
    }

    public function test_paid_legacy_order_without_payment_evidence_never_creates_income_on_reporting(): void
    {
        Order::create([
            'customer_id' => Customer::create(['name' => 'Historical customer'])->id, 'source' => 'admin', 'status' => 'waiting', 'payment_status' => 'paid', 'payment_method' => 'cash', 'condition' => 'raw',
            'delivery_type' => 'pickup', 'delivery_period' => 'night', 'delivery_date' => '2026-08-25',
            'customer_name_snapshot' => 'Historical customer', 'items_cooked_subtotal' => 1000, 'raw_discount' => 0,
            'items_subtotal' => 1000, 'toppings_subtotal' => 0, 'shipping_fee_snapshot' => 0, 'combined_dozen_savings' => 0, 'discount_amount' => 0, 'total' => 1000,
        ]);
        $this->get('/admin/management')->assertOk()->assertInertia(fn (Assert $p) => $p->where('summary.period_income', 0)->where('summary.total_balance', 0)->has('transactions.data', 0));
        $this->assertDatabaseCount('management_order_payments', 0);
        $this->assertDatabaseCount('financial_transactions', 0);
        $this->assertDatabaseCount('ledger_entries', 0);
    }

    public function test_distributions_and_their_reversals_preserve_neutrality_and_separate_applied_amounts(): void
    {
        $source = Wallet::create(['name' => 'Distribution source']);
        $destination = Wallet::create(['name' => 'Distribution destination']);
        $this->movement($source, 'opening', 2000, '2026-08-01');
        $this->post('/admin/management/distribution-rules', ['name' => 'One destination', 'allocations' => [['wallet_id' => $destination->id, 'percentage_bps' => 10000, 'sort_order' => 0]]])->assertRedirect()->assertSessionHasNoErrors();
        $version = DistributionRuleVersion::sole();
        $this->post('/admin/management/distributions', ['source_wallet_id' => $source->id, 'rule_version_id' => $version->id, 'amount' => 1000, 'effective_date' => '2026-08-20'])->assertRedirect()->assertSessionHasNoErrors();
        $distribution = ManagementDistribution::sole();
        $this->get('/admin/management?from=2026-08-19&to=2026-08-25')->assertInertia(fn (Assert $p) => $p
            ->where('summary.period_distribution_applied', 1000)->where('summary.period_distribution_reversed', 0)->where('summary.period_distribution', 1000)
            ->where('summary.period_income', 0)->where('summary.total_balance', 2000)->where('summary.reconciliation_difference', 0));
        $this->post('/admin/management/transactions/'.$distribution->financial_transaction_id.'/reverse', ['effective_date' => '2026-08-21'])->assertRedirect()->assertSessionHasNoErrors();
        $this->get('/admin/management?from=2026-08-19&to=2026-08-25')->assertInertia(fn (Assert $p) => $p
            ->where('summary.period_distribution_applied', 1000)->where('summary.period_distribution_reversed', 1000)->where('summary.period_distribution', 0)
            ->where('summary.period_operating_result', 0)->where('summary.period_balance_change', 0));
    }

    private function movement(Wallet $wallet, string $type, int $amount, string $date): FinancialTransaction
    {
        $this->post('/admin/management/transactions', ['wallet_id' => $wallet->id, 'type' => $type, 'amount' => $amount, 'effective_date' => $date])->assertRedirect()->assertSessionHasNoErrors();

        return FinancialTransaction::latest('id')->firstOrFail();
    }
}
