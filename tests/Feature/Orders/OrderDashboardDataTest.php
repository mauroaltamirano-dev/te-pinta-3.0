<?php

namespace Tests\Feature\Orders;

use App\Modules\Customers\Models\Customer;
use App\Modules\Identity\Models\User;
use App\Modules\Orders\Models\Order;
use App\Modules\Products\Models\Category;
use App\Modules\Products\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

final class OrderDashboardDataTest extends TestCase
{
    use RefreshDatabase;

    public function test_orders_index_exposes_real_most_ordered_products_and_delivery_distribution(): void
    {
        Storage::fake('public');
        $admin = User::factory()->administrator()->create();
        $customer = Customer::query()->create(['name' => 'Cliente']);
        $category = Category::query()->create([
            'name' => 'Empanadas', 'slug' => 'empanadas', 'sort_order' => 1, 'is_active' => true,
        ]);
        $products = collect([
            ['Carne', 'carne', 'products/carne.jpg'],
            ['Pollo', 'pollo', null],
            ['Humita', 'humita', null],
            ['Queso', 'queso', null],
        ])->map(fn (array $data): Product => Product::query()->create([
            'category_id' => $category->id,
            'name' => $data[0],
            'slug' => $data[1],
            'image_path' => $data[2],
            'unit_price' => 1000,
            'half_dozen_price' => 5000,
            'dozen_price' => 9000,
            'is_available' => true,
            'sort_order' => 1,
        ]));

        $deliveryOrder = $this->order($customer, 'TP-000101', 'delivery', 'cooked');
        $pickupOrder = $this->order($customer, 'TP-000102', 'pickup', 'raw');
        $deletedOrder = $this->order($customer, 'TP-000103', 'delivery', 'cooked');

        $this->directItem($deliveryOrder, $products[0], 12);
        $this->directItem($pickupOrder, $products[1], 7);
        $combined = $deliveryOrder->items()->create([
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
        $combined->compositions()->createMany([
            ['product_id' => $products[0]->id, 'product_name' => 'Carne', 'quantity' => 2],
            ['product_id' => $products[2]->id, 'product_name' => 'Humita', 'quantity' => 6],
            ['product_id' => $products[3]->id, 'product_name' => 'Queso', 'quantity' => 4],
        ]);
        $this->directItem($deletedOrder, $products[3], 100);
        $deletedOrder->delete();

        $this->actingAs($admin)->get(route('admin.orders.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('mostOrderedProducts.0.product_id', $products[0]->id)
                ->where('mostOrderedProducts.0.name', 'Carne')
                ->where('mostOrderedProducts.0.units_count', 14)
                ->where('mostOrderedProducts.0.image_url', Storage::disk('public')->url('products/carne.jpg'))
                ->where('mostOrderedProducts.1.product_id', $products[1]->id)
                ->where('mostOrderedProducts.1.units_count', 7)
                ->where('mostOrderedProducts.2.product_id', $products[2]->id)
                ->where('mostOrderedProducts.2.units_count', 6)
                ->has('mostOrderedProducts', 3)
                ->where('deliveryTypeDistribution', [
                    ['type' => 'delivery', 'orders_count' => 1],
                    ['type' => 'pickup', 'orders_count' => 1],
                ])
                ->where('conditionDistribution', [
                    ['condition' => 'raw', 'orders_count' => 1],
                    ['condition' => 'cooked', 'orders_count' => 1],
                ]));
    }

    private function order(Customer $customer, string $publicId, string $deliveryType, string $condition): Order
    {
        return Order::query()->forceCreate([
            'public_id' => $publicId,
            'customer_id' => $customer->id,
            'customer_name_snapshot' => $customer->name,
            'delivery_type' => $deliveryType,
            'condition' => $condition,
            'delivery_address_snapshot' => $deliveryType === 'delivery' ? 'Dirección 123' : null,
            'delivery_date' => now()->toDateString(),
        ]);
    }

    private function directItem(Order $order, Product $product, int $units): void
    {
        $order->items()->create([
            'product_id' => $product->id,
            'product_name' => $product->name,
            'pricing_mode' => 'unit',
            'quantity' => $units,
            'units_count' => $units,
            'unit_price' => 1000,
            'line_total' => $units * 1000,
            'regular_price_total' => $units * 1000,
            'savings' => 0,
        ]);
    }
}
