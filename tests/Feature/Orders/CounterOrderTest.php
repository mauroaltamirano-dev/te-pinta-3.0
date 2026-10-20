<?php

namespace Tests\Feature\Orders;

use App\Modules\Identity\Enums\Role;
use App\Modules\Identity\Models\User;
use App\Modules\Orders\Enums\DeliveryType;
use App\Modules\Orders\Enums\OrderSource;
use App\Modules\Orders\Enums\OrderStatus;
use App\Modules\Orders\Models\Order;
use App\Modules\Printing\Models\PrintJob;
use App\Modules\Products\Models\Category;
use App\Modules\Products\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** "Consumo en el local / mostrador": a quick order without customer data that prints a kitchen ticket. */
final class CounterOrderTest extends TestCase
{
    use RefreshDatabase;

    private Product $coke;

    private User $cashier;

    protected function setUp(): void
    {
        parent::setUp();
        $category = Category::query()->create(['name' => 'Bebidas', 'slug' => 'bebidas', 'sort_order' => 1, 'is_active' => true]);
        $this->coke = Product::query()->create([
            'category_id' => $category->id, 'name' => 'Coca-Cola', 'slug' => 'coca-cola', 'pricing_family' => 'unit',
            'unit_price' => 2500, 'half_dozen_price' => 2500, 'dozen_price' => 2500, 'is_available' => true, 'sort_order' => 1,
        ]);
        $this->cashier = User::factory()->withRole(Role::Cashier)->create();
    }

    public function test_a_counter_order_needs_no_customer_and_prints_a_kitchen_ticket(): void
    {
        $response = $this->actingAs($this->cashier)->postJson(route('admin.orders.store'), $this->payload())->assertCreated();

        $order = Order::query()->where('public_id', $response->json('data.order_id'))->sole();
        $this->assertSame(
            [OrderSource::Pos, DeliveryType::DineIn, OrderStatus::Waiting, null, null, null, 5000],
            [$order->source, $order->delivery_type, $order->status, $order->customer_id, $order->delivery_date, $order->delivery_period, (int) $order->total],
        );
        $ticket = PrintJob::query()->sole();
        $this->assertSame(["order:{$order->id}:kitchen", 'Mostrador'], [$ticket->dedupe_key, $ticket->payload['title']]);
        $this->assertSame([['name' => 'Coca-Cola', 'quantity' => 2]], $ticket->payload['lines']);
    }

    public function test_a_counter_order_takes_no_schedule_or_address(): void
    {
        $this->actingAs($this->cashier)->postJson(route('admin.orders.store'), [
            ...$this->payload(), 'delivery_period' => 'night', 'delivery_date' => now()->toDateString(), 'delivery_address' => 'Calle 1',
        ])->assertUnprocessable()->assertJsonValidationErrors(['delivery_period', 'delivery_date', 'delivery_address']);

        $this->assertSame(0, Order::query()->count());
    }

    public function test_pickup_and_delivery_orders_still_require_a_customer(): void
    {
        $this->actingAs($this->cashier)->postJson(route('admin.orders.store'), [
            ...$this->payload(), 'delivery_type' => 'pickup', 'delivery_period' => 'night', 'delivery_date' => now()->toDateString(),
        ])->assertUnprocessable()->assertJsonValidationErrors(['customer']);
    }

    /** @return array<string, mixed> */
    private function payload(): array
    {
        return [
            'items' => [['product_id' => $this->coke->id, 'quantity' => 2]], 'addons' => [], 'condition' => 'cooked',
            'delivery_type' => 'dine_in', 'delivery_time' => null, 'notes' => null, 'customer_id' => null, 'payment_status' => 'unpaid',
        ];
    }
}
