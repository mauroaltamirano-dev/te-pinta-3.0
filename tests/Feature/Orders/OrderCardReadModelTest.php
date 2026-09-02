<?php

namespace Tests\Feature\Orders;

use App\Modules\Customers\Models\Customer;
use App\Modules\Identity\Models\User;
use App\Modules\Orders\Models\Order;
use App\Modules\Products\Models\Category;
use App\Modules\Products\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

final class OrderCardReadModelTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Customer $customer;

    private Product $product;

    private Product $otherProduct;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->administrator()->create();
        $this->customer = Customer::query()->create(['name' => 'Cliente histórico']);
        $category = Category::query()->create([
            'name' => 'Empanadas',
            'slug' => 'empanadas',
            'sort_order' => 1,
            'is_active' => true,
        ]);
        $this->product = $this->product($category, 'Carne', 'carne');
        $this->otherProduct = $this->product($category, 'Humita', 'humita');
    }

    public function test_orders_index_exposes_item_composition_and_topping_snapshots_for_cards(): void
    {
        $order = $this->order('TP-900001', 19000, 16000, 3000);
        $item = $order->items()->create([
            'product_id' => null,
            'product_name' => 'Docena combinada histórica',
            'pricing_mode' => 'combined_dozen',
            'quantity' => 1,
            'units_count' => 12,
            'unit_price' => 16000,
            'line_total' => 16000,
            'regular_price_total' => 16000,
            'savings' => 0,
        ]);
        $item->compositions()->createMany([
            [
                'product_id' => $this->product->id,
                'product_name' => 'Carne histórica',
                'quantity' => 5,
            ],
            [
                'product_id' => $this->otherProduct->id,
                'product_name' => 'Humita histórica',
                'quantity' => 7,
            ],
        ]);
        $order->toppings()->create([
            'order_addon_id' => null,
            'name' => 'Salsa histórica',
            'quantity' => 2,
            'unit_price' => 1500,
            'subtotal' => 3000,
        ]);

        $this->product->update(['name' => 'Carne actual', 'image_path' => 'products/current.jpg']);
        $this->otherProduct->update(['name' => 'Humita actual']);

        $this->actingAs($this->admin)->get(route('admin.orders.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/orders/index')
                ->where('orders.data.0.id', 'TP-900001')
                ->where('orders.data.0.items.0.product_name', 'Docena combinada histórica')
                ->has('orders.data.0.items.0.composition', 2)
                ->where('orders.data.0.items.0.composition.0.product_name', 'Carne histórica')
                ->where('orders.data.0.items.0.composition.1.product_name', 'Humita histórica')
                ->where('orders.data.0.toppings.0.name', 'Salsa histórica'));
    }

    public function test_orders_card_read_model_query_count_does_not_grow_per_order(): void
    {
        $this->combinedOrder('TP-900010');
        $this->actingAs($this->admin);

        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->get(route('admin.orders.index'))->assertOk();
        $singleOrderQueryCount = count(DB::getQueryLog());
        DB::disableQueryLog();

        foreach (range(11, 14) as $suffix) {
            $this->combinedOrder("TP-9000{$suffix}");
        }

        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->get(route('admin.orders.index'))->assertOk();
        $multipleOrdersQueryCount = count(DB::getQueryLog());
        DB::disableQueryLog();

        $this->assertSame($singleOrderQueryCount, $multipleOrdersQueryCount);
    }

    public function test_orders_index_groups_states_and_applies_whitelisted_stable_sorting(): void
    {
        $waiting = $this->order('TP-900020', 22000, 22000);
        $prepared = $this->order('TP-900021', 18000, 18000);
        $prepared->update(['status' => 'prepared']);
        $delivered = $this->order('TP-900022', 18000, 18000);
        $delivered->update(['status' => 'delivered']);
        $cancelled = $this->order('TP-900023', 12000, 12000);
        $cancelled->update(['status' => 'cancelled']);

        $this->actingAs($this->admin)
            ->get(route('admin.orders.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('filters.group', 'all')
                ->where('orders.total', 3)
                ->where('orders.data.0.id', $delivered->public_id)
                ->where('orders.data.1.id', $prepared->public_id)
                ->where('orders.data.2.id', $waiting->public_id));

        $this->actingAs($this->admin)
            ->get(route('admin.orders.index', [
                'group' => 'in_progress',
                'sort' => 'total',
                'direction' => 'asc',
                'view' => 'list',
            ]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('filters.group', 'in_progress')
                ->where('filters.sort', 'total')
                ->where('filters.direction', 'asc')
                ->where('filters.view', 'list')
                ->where('orders.total', 2)
                ->where('orders.data.0.id', $prepared->public_id)
                ->where('orders.data.1.id', $waiting->public_id));

        $this->get(route('admin.orders.index', [
            'group' => 'cancelled',
            'status' => 'delivered',
        ]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('filters.group', 'delivered')
                ->where('orders.total', 1)
                ->where('orders.data.0.id', $delivered->public_id));

        $this->get(route('admin.orders.index', ['group' => 'cancelled']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('orders.total', 1)
                ->where('orders.data.0.id', $cancelled->public_id));

        $this->get(route('admin.orders.index', ['status' => 'cancelled']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('filters.group', 'cancelled')
                ->where('filters.status', 'cancelled')
                ->where('orders.total', 1)
                ->where('orders.data.0.id', $cancelled->public_id));

        $this->from(route('admin.orders.index'))
            ->get(route('admin.orders.index', ['sort' => 'total desc; drop table orders']))
            ->assertRedirect(route('admin.orders.index'))
            ->assertSessionHasErrors('sort');

    }

    private function product(Category $category, string $name, string $slug): Product
    {
        return Product::query()->create([
            'category_id' => $category->id,
            'name' => $name,
            'slug' => $slug,
            'unit_price' => 1600,
            'half_dozen_price' => 9000,
            'dozen_price' => 16000,
            'is_available' => true,
            'sort_order' => 1,
        ]);
    }

    private function order(
        string $publicId,
        int $total,
        int $itemsSubtotal,
        int $toppingsSubtotal = 0,
    ): Order {
        return Order::query()->forceCreate([
            'public_id' => $publicId,
            'customer_id' => $this->customer->id,
            'customer_name_snapshot' => $this->customer->name,
            'status' => 'waiting',
            'payment_status' => 'unpaid',
            'condition' => 'raw',
            'delivery_type' => 'pickup',
            'delivery_period' => 'night',
            'delivery_date' => '2026-08-16',
            'items_subtotal' => $itemsSubtotal,
            'toppings_subtotal' => $toppingsSubtotal,
            'shipping_fee_snapshot' => 0,
            'combined_dozen_savings' => 0,
            'discount_amount' => 0,
            'total' => $total,
        ]);
    }

    private function combinedOrder(string $publicId): void
    {
        $order = $this->order($publicId, 16000, 16000);
        $item = $order->items()->create([
            'product_id' => null,
            'product_name' => 'Docena combinada',
            'pricing_mode' => 'combined_dozen',
            'quantity' => 1,
            'units_count' => 12,
            'unit_price' => 16000,
            'line_total' => 16000,
            'regular_price_total' => 16000,
            'savings' => 0,
        ]);
        $item->compositions()->createMany([
            ['product_id' => $this->product->id, 'product_name' => 'Carne', 'quantity' => 6],
            ['product_id' => $this->otherProduct->id, 'product_name' => 'Humita', 'quantity' => 6],
        ]);
    }
}
