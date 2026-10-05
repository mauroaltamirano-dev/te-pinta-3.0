<?php

namespace Tests\Feature\Dashboard;

use App\Modules\Customers\Models\Customer;
use App\Modules\Dashboard\Enums\DashboardPeriod;
use App\Modules\Dashboard\Queries\BuildOperationalDashboard;
use App\Modules\Identity\Models\User;
use App\Modules\Orders\Models\Order;
use App\Modules\Products\Models\Category;
use App\Modules\Products\Models\Product;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

final class OperationalDashboardTest extends TestCase
{
    use RefreshDatabase;

    private int $orderSequence = 900000;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(CarbonImmutable::parse('2026-08-19 15:00:00 UTC'));
    }

    public function test_empty_dashboard_has_stable_zero_filled_shapes(): void
    {
        $this->actingAs(User::factory()->administrator()->create())
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/dashboard')
                ->where('dashboard.period', 'today')
                ->where('dashboard.range', [
                    'start_date' => '2026-08-19',
                    'end_date' => '2026-08-19',
                ])
                ->where('dashboard.kpis', [
                    'sales_total' => 0,
                    'collected_total' => 0,
                    'orders_count' => 0,
                    'pending_confirmation_count' => 0,
                    'unpaid_count' => 0,
                    'empanadas_count' => 0,
                ])
                ->where('dashboard.today_status_distribution', [
                    ['status' => 'pending_confirmation', 'orders_count' => 0],
                    ['status' => 'waiting', 'orders_count' => 0],
                    ['status' => 'prepared', 'orders_count' => 0],
                    ['status' => 'delivered', 'orders_count' => 0],
                ])
                ->where('dashboard.urgent_orders', [])
                ->where('dashboard.today_orders', [])
                ->where('dashboard.top_products', [])
                ->where('dashboard.evolution', [
                    ['bucket' => 'midday', 'total' => 0],
                    ['bucket' => 'afternoon', 'total' => 0],
                    ['bucket' => 'night', 'total' => 0],
                ])
                ->where('dashboard.delivery_distribution', [
                    ['type' => 'delivery', 'orders_count' => 0],
                    ['type' => 'pickup', 'orders_count' => 0],
                ])
                ->where('dashboard.condition_distribution', [
                    ['condition' => 'raw', 'units_count' => 0],
                    ['condition' => 'cooked', 'units_count' => 0],
                ])
                ->where('selectedOrder', null)
                ->where('selectedOrderSummary', null));
    }

    public function test_kpis_use_delivery_date_and_apply_status_payment_and_soft_delete_rules(): void
    {
        $customer = $this->customer();
        $today = '2026-08-19';

        $this->order($customer, status: 'waiting', total: 10_000, deliveryDate: $today, deliveryPeriod: 'midday');
        $this->order($customer, status: 'prepared', paymentStatus: 'paid', total: 20_000, deliveryDate: $today, deliveryType: 'delivery', deliveryPeriod: 'afternoon');
        $this->order($customer, status: 'delivered', paymentStatus: 'paid', total: 30_000, deliveryDate: $today, deliveryPeriod: 'night');
        $this->order($customer, status: 'pending_confirmation', paymentStatus: 'paid', total: 40_000, deliveryDate: $today, deliveryType: 'delivery');
        $this->order($customer, status: 'cancelled', paymentStatus: 'paid', total: 50_000, deliveryDate: $today);
        $deleted = $this->order($customer, status: 'delivered', paymentStatus: 'paid', total: 60_000, deliveryDate: $today);
        $deleted->delete();
        $this->order(
            $customer,
            status: 'delivered',
            paymentStatus: 'paid',
            total: 70_000,
            deliveryDate: '2026-08-20',
            createdAt: '2026-08-19 10:00:00',
        );
        $this->order(
            $customer,
            status: 'waiting',
            total: 80_000,
            deliveryDate: $today,
            deliveryPeriod: 'midday',
            createdAt: '2026-08-01 10:00:00',
        );

        $this->actingAs(User::factory()->administrator()->create())
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('dashboard.kpis.sales_total', 140_000)
                ->where('dashboard.kpis.collected_total', 50_000)
                ->where('dashboard.kpis.orders_count', 5)
                ->where('dashboard.kpis.pending_confirmation_count', 1)
                ->where('dashboard.kpis.unpaid_count', 2)
                ->where('dashboard.delivery_distribution', [
                    ['type' => 'delivery', 'orders_count' => 2],
                    ['type' => 'pickup', 'orders_count' => 3],
                ])
                ->where('dashboard.evolution', [
                    ['bucket' => 'midday', 'total' => 90_000],
                    ['bucket' => 'afternoon', 'total' => 20_000],
                    ['bucket' => 'night', 'total' => 30_000],
                ]));
    }

    public function test_week_and_month_are_calendar_periods_with_zero_filled_daily_evolution(): void
    {
        $customer = $this->customer();
        $this->order($customer, total: 17_000, deliveryDate: '2026-08-17');
        $this->order($customer, total: 19_000, deliveryDate: '2026-08-19');
        $this->order($customer, total: 23_000, deliveryDate: '2026-08-23');
        $this->order($customer, total: 1_000, deliveryDate: '2026-08-01');
        $this->order($customer, total: 31_000, deliveryDate: '2026-08-31');
        $this->order($customer, total: 50_000, deliveryDate: '2026-09-01');

        $admin = User::factory()->administrator()->create();

        $this->actingAs($admin)->get(route('admin.dashboard', ['period' => 'week']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('dashboard.period', 'week')
                ->where('dashboard.range', [
                    'start_date' => '2026-08-17',
                    'end_date' => '2026-08-23',
                ])
                ->where('dashboard.kpis.sales_total', 59_000)
                ->has('dashboard.evolution', 7)
                ->where('dashboard.evolution.0', ['bucket' => '2026-08-17', 'total' => 17_000])
                ->where('dashboard.evolution.1', ['bucket' => '2026-08-18', 'total' => 0])
                ->where('dashboard.evolution.6', ['bucket' => '2026-08-23', 'total' => 23_000]));

        $this->actingAs($admin)->get(route('admin.dashboard', ['period' => 'month']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('dashboard.period', 'month')
                ->where('dashboard.range', [
                    'start_date' => '2026-08-01',
                    'end_date' => '2026-08-31',
                ])
                ->where('dashboard.kpis.sales_total', 91_000)
                ->has('dashboard.evolution', 31)
                ->where('dashboard.evolution.0', ['bucket' => '2026-08-01', 'total' => 1_000])
                ->where('dashboard.evolution.18', ['bucket' => '2026-08-19', 'total' => 19_000])
                ->where('dashboard.evolution.30', ['bucket' => '2026-08-31', 'total' => 31_000]));
    }

    public function test_all_period_uses_the_full_active_delivery_range_and_monthly_evolution(): void
    {
        $customer = $this->customer();
        $this->order($customer, total: 10_000, deliveryDate: '2026-06-15');
        $this->order($customer, total: 20_000, deliveryDate: '2026-08-19');
        $this->order($customer, total: 30_000, deliveryDate: '2026-10-01');
        $this->order($customer, status: 'cancelled', total: 40_000, deliveryDate: '2026-05-01');
        $deleted = $this->order($customer, total: 50_000, deliveryDate: '2026-11-01');
        $deleted->delete();

        $this->actingAs(User::factory()->administrator()->create())
            ->get(route('admin.dashboard', ['period' => 'all']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('dashboard.period', 'all')
                ->where('dashboard.range', [
                    'start_date' => '2026-06-15',
                    'end_date' => '2026-10-01',
                ])
                ->where('dashboard.kpis.sales_total', 60_000)
                ->where('dashboard.kpis.orders_count', 3)
                ->where('dashboard.evolution', [
                    ['bucket' => '2026-06', 'total' => 10_000],
                    ['bucket' => '2026-07', 'total' => 0],
                    ['bucket' => '2026-08', 'total' => 20_000],
                    ['bucket' => '2026-09', 'total' => 0],
                    ['bucket' => '2026-10', 'total' => 30_000],
                ]));
    }

    public function test_urgent_and_today_orders_have_distinct_operational_sorting(): void
    {
        $customer = $this->customer();
        $pendingAfternoon = $this->order($customer, status: 'pending_confirmation', deliveryPeriod: 'afternoon', deliveryTime: '10:00');
        $pendingMiddayNoTime = $this->order($customer, status: 'pending_confirmation', deliveryPeriod: 'midday');
        $pendingMiddayLate = $this->order($customer, status: 'pending_confirmation', deliveryPeriod: 'midday', deliveryTime: '13:00');
        $waitingMiddayEarly = $this->order($customer, status: 'waiting', deliveryPeriod: 'midday', deliveryTime: '12:00');
        $preparedNight = $this->order($customer, status: 'prepared', deliveryPeriod: 'night', deliveryTime: '20:00');
        $deliveredMidday = $this->order($customer, status: 'delivered', deliveryPeriod: 'midday', deliveryTime: '11:00');
        $this->order($customer, status: 'cancelled', deliveryPeriod: 'midday', deliveryTime: '09:00');
        $this->order($customer, status: 'waiting', deliveryDate: '2026-08-20', deliveryPeriod: 'midday', deliveryTime: '08:00');

        $this->actingAs(User::factory()->administrator()->create())
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where(
                    'dashboard.urgent_orders',
                    fn (Collection $orders): bool => $orders->pluck('id')->all() === [
                        $pendingMiddayLate->public_id,
                        $pendingMiddayNoTime->public_id,
                        $pendingAfternoon->public_id,
                        $waitingMiddayEarly->public_id,
                        $preparedNight->public_id,
                    ],
                )
                ->where(
                    'dashboard.today_orders',
                    fn (Collection $orders): bool => $orders->pluck('id')->all() === [
                        $deliveredMidday->public_id,
                        $waitingMiddayEarly->public_id,
                        $pendingMiddayLate->public_id,
                        $pendingMiddayNoTime->public_id,
                        $pendingAfternoon->public_id,
                        $preparedNight->public_id,
                    ],
                ));
    }

    public function test_product_ranking_and_condition_units_use_persisted_real_quantities(): void
    {
        [$saltena, $caprese, $humita] = $this->products();
        $customer = $this->customer();

        $rawPickup = $this->order($customer, status: 'waiting', condition: 'raw', deliveryType: 'pickup');
        $this->directItem($rawPickup, $saltena, 'Salteña histórica', 'dozen', 1, 12);

        $cookedDelivery = $this->order($customer, status: 'prepared', condition: 'cooked', deliveryType: 'delivery');
        $this->directItem($cookedDelivery, $caprese, 'Caprese', 'half_dozen', 1, 6);
        $unitProduct = Product::query()->create([
            'category_id' => $saltena->category_id,
            'pricing_family' => 'unit',
            'name' => 'Bebida histórica',
            'slug' => 'bebida-historica',
            'unit_price' => 2_500,
            'half_dozen_price' => 2_500,
            'dozen_price' => 2_500,
            'is_available' => true,
            'sort_order' => 99,
        ]);
        $this->directItem($cookedDelivery, $unitProduct, 'Bebida histórica', 'unit', 2, 2);
        $combined = $cookedDelivery->items()->create([
            'product_id' => null,
            'product_name' => 'Docena combinada',
            'pricing_mode' => 'combined_dozen',
            'quantity' => 1,
            'units_count' => 12,
            'unit_price' => 12_000,
            'line_total' => 12_000,
            'regular_price_total' => 12_000,
            'savings' => 0,
        ]);
        $combined->compositions()->createMany([
            ['product_id' => $saltena->id, 'product_name' => 'Salteña histórica', 'quantity' => 7],
            ['product_id' => $humita->id, 'product_name' => 'Humita', 'quantity' => 5],
        ]);

        $pendingDelivery = $this->order($customer, status: 'pending_confirmation', condition: 'cooked', deliveryType: 'delivery');
        $this->directItem($pendingDelivery, $saltena, 'No cuenta', 'unit', 100, 100);
        $cancelled = $this->order($customer, status: 'cancelled', condition: 'raw', deliveryType: 'pickup');
        $this->directItem($cancelled, $caprese, 'No cuenta', 'unit', 100, 100);
        $deleted = $this->order($customer, status: 'delivered', condition: 'raw', deliveryType: 'pickup');
        $this->directItem($deleted, $humita, 'No cuenta', 'unit', 100, 100);
        $deleted->delete();

        $saltena->update(['name' => 'Nombre actual distinto']);
        $saltena->delete();

        $this->actingAs(User::factory()->administrator()->create())
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('dashboard.kpis.empanadas_count', 32)
                ->where('dashboard.top_products', [
                    ['product_id' => $saltena->id, 'name' => 'Salteña histórica', 'units_count' => 19],
                    ['product_id' => $caprese->id, 'name' => 'Caprese', 'units_count' => 6],
                    ['product_id' => $humita->id, 'name' => 'Humita', 'units_count' => 5],
                    ['product_id' => $unitProduct->id, 'name' => 'Bebida histórica', 'units_count' => 2],
                ])
                ->where('dashboard.condition_distribution', [
                    ['condition' => 'raw', 'units_count' => 12],
                    ['condition' => 'cooked', 'units_count' => 20],
                ])
                ->where('dashboard.delivery_distribution', [
                    ['type' => 'delivery', 'orders_count' => 2],
                    ['type' => 'pickup', 'orders_count' => 1],
                ]));
    }

    public function test_product_ranking_returns_every_sold_variety(): void
    {
        $products = $this->products();
        $categoryId = $products[0]->category_id;

        foreach (range(4, 6) as $index) {
            $products[] = Product::query()->create([
                'category_id' => $categoryId,
                'name' => "Variedad {$index}",
                'slug' => 'dashboard-product-'.$this->orderSequence.'-'.$index,
                'unit_price' => 1_000,
                'half_dozen_price' => 5_000,
                'dozen_price' => 9_000,
                'is_available' => true,
                'sort_order' => $index,
            ]);
        }

        $order = $this->order($this->customer());
        foreach ($products as $index => $product) {
            $units = count($products) - $index;
            $this->directItem($order, $product, $product->name, 'unit', $units, $units);
        }

        $this->actingAs(User::factory()->administrator()->create())
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('dashboard.top_products', 6)
                ->where('dashboard.top_products.0.units_count', 6)
                ->where('dashboard.top_products.5.units_count', 1));
    }

    public function test_selected_active_order_uses_canonical_admin_resource_and_summary(): void
    {
        $product = $this->products()[0];
        $customer = $this->customer();
        $order = $this->order($customer, status: 'waiting', total: 12_000);
        $this->directItem($order, $product, 'Salteña guardada', 'dozen', 1, 12);
        $order->toppings()->create([
            'order_addon_id' => null,
            'name' => 'Yasgua guardada',
            'quantity' => 1,
            'unit_price' => 0,
            'subtotal' => 0,
        ]);

        $admin = User::factory()->administrator()->create();
        $this->actingAs($admin)
            ->get(route('admin.dashboard', ['period' => 'week', 'order' => $order->public_id]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('dashboard.period', 'week')
                ->where('selectedOrder.id', $order->public_id)
                ->where('selectedOrder.items.0.product_name', 'Salteña guardada')
                ->where('selectedOrder.items.0.units_count', 12)
                ->where('selectedOrder.toppings.0.name', 'Yasgua guardada')
                ->where('selectedOrderSummary', fn (string $summary): bool => str_contains($summary, $order->public_id)
                    && str_contains($summary, 'Salteña guardada')));

        $order->delete();
        $this->actingAs($admin)
            ->get(route('admin.dashboard', ['order' => $order->public_id]))
            ->assertNotFound();
    }

    public function test_dashboard_requires_admin_and_validates_filters(): void
    {
        $this->get(route('admin.dashboard'))->assertRedirect(route('login'));
        $this->actingAs(User::factory()->create())
            ->get(route('admin.dashboard'))
            ->assertForbidden();

        $admin = User::factory()->administrator()->create();
        $this->actingAs($admin)
            ->get(route('admin.dashboard', ['period' => 'rolling', 'order' => 'invalid']))
            ->assertSessionHasErrors(['period', 'order']);
    }

    public function test_read_model_has_a_fixed_query_count(): void
    {
        $customer = $this->customer();
        foreach (range(1, 15) as $index) {
            $this->order($customer, total: $index * 1_000);
        }

        DB::flushQueryLog();
        DB::enableQueryLog();
        $dashboard = $this->app->make(BuildOperationalDashboard::class)
            ->execute(DashboardPeriod::Today);
        $queryCount = count(DB::getQueryLog());
        DB::disableQueryLog();

        $this->assertSame(7, $queryCount);
        $this->assertCount(15, $dashboard['today_orders']);

        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->app->make(BuildOperationalDashboard::class)
            ->execute(DashboardPeriod::All);
        $allTimeQueryCount = count(DB::getQueryLog());
        DB::disableQueryLog();

        $this->assertSame(7, $allTimeQueryCount);
    }

    private function customer(): Customer
    {
        return Customer::query()->create(['name' => 'Cliente Dashboard']);
    }

    /** @return array{Product, Product, Product} */
    private function products(): array
    {
        $category = Category::query()->create([
            'name' => 'Empanadas',
            'slug' => 'empanadas-'.$this->orderSequence,
            'sort_order' => 1,
            'is_active' => true,
        ]);

        $products = [];
        foreach (['Salteña actual', 'Caprese', 'Humita'] as $index => $name) {
            $products[] = Product::query()->create([
                'category_id' => $category->id,
                'name' => $name,
                'slug' => 'dashboard-product-'.$this->orderSequence.'-'.$index,
                'unit_price' => 1_000,
                'half_dozen_price' => 5_000,
                'dozen_price' => 9_000,
                'is_available' => true,
                'sort_order' => $index,
            ]);
        }

        return [$products[0], $products[1], $products[2]];
    }

    private function order(
        Customer $customer,
        string $status = 'waiting',
        string $paymentStatus = 'unpaid',
        int $total = 0,
        string $deliveryDate = '2026-08-19',
        string $deliveryPeriod = 'night',
        ?string $deliveryTime = null,
        string $deliveryType = 'pickup',
        string $condition = 'raw',
        ?string $createdAt = null,
    ): Order {
        $publicId = 'TP-'.(++$this->orderSequence);

        return Order::query()->forceCreate([
            'public_id' => $publicId,
            'customer_id' => $customer->id,
            'customer_name_snapshot' => $customer->name,
            'status' => $status,
            'payment_status' => $paymentStatus,
            'payment_method' => $paymentStatus === 'paid' ? 'cash' : null,
            'condition' => $condition,
            'delivery_type' => $deliveryType,
            'delivery_period' => $deliveryPeriod,
            'delivery_date' => $deliveryDate,
            'delivery_time' => $deliveryTime,
            'delivery_address_snapshot' => $deliveryType === 'delivery' ? 'Calle 123' : null,
            'items_subtotal' => $total,
            'total' => $total,
            'created_at' => $createdAt ?? now(),
            'updated_at' => $createdAt ?? now(),
        ]);
    }

    private function directItem(
        Order $order,
        Product $product,
        string $snapshotName,
        string $pricingMode,
        int $quantity,
        int $units,
    ): void {
        $lineTotal = $units * 1_000;
        $order->items()->create([
            'product_id' => $product->id,
            'product_name' => $snapshotName,
            'pricing_mode' => $pricingMode,
            'quantity' => $quantity,
            'units_count' => $units,
            'unit_price' => $lineTotal,
            'line_total' => $lineTotal,
            'regular_price_total' => $lineTotal,
            'savings' => 0,
        ]);
    }
}
