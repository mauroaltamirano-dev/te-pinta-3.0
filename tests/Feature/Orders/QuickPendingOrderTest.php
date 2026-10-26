<?php

namespace Tests\Feature\Orders;

use App\Modules\Identity\Enums\Role;
use App\Modules\Identity\Models\User;
use App\Modules\Orders\Enums\DeliveryType;
use App\Modules\Orders\Enums\OrderSource;
use App\Modules\Orders\Enums\OrderStatus;
use App\Modules\Orders\Models\Order;
use App\Modules\Orders\Support\OrderBusinessDate;
use App\Modules\Printing\Models\PrintJob;
use App\Modules\Products\Models\Category;
use App\Modules\Products\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** "Pedido rápido": products only, a same-day pickup awaiting confirmation that prints its kitchen ticket at once. */
final class QuickPendingOrderTest extends TestCase
{
    use RefreshDatabase;

    private Product $coke;

    private User $cashier;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $category = Category::query()->create(['name' => 'Bebidas', 'slug' => 'bebidas', 'sort_order' => 1, 'is_active' => true]);
        $this->coke = Product::query()->create([
            'category_id' => $category->id, 'name' => 'Coca-Cola', 'slug' => 'coca-cola', 'pricing_family' => 'unit',
            'unit_price' => 2500, 'half_dozen_price' => 2500, 'dozen_price' => 2500, 'is_available' => true, 'sort_order' => 1,
        ]);
        $this->cashier = User::factory()->withRole(Role::Cashier)->create();
    }

    public function test_a_quick_order_needs_only_products_and_waits_for_confirmation_today(): void
    {
        $response = $this->actingAs($this->cashier)->postJson(route('admin.orders.store'), $this->payload())
            ->assertCreated()
            ->assertJsonPath('data.status', 'pending_confirmation')
            ->assertJsonPath('data.total', 5000);

        $order = Order::query()->where('public_id', $response->json('data.order_id'))->sole();
        $this->assertSame(
            [OrderSource::Admin, DeliveryType::Pickup, OrderStatus::PendingConfirmation, null, null, null, null],
            [$order->source, $order->delivery_type, $order->status, $order->customer_id, $order->customer_name_snapshot, $order->delivery_period, $order->delivery_time],
        );
        $this->assertSame(app(OrderBusinessDate::class)->todayString(), $order->delivery_date?->toDateString());
        $this->assertStringContainsString('5.000', (string) $response->json('data.summary'));
    }

    public function test_a_quick_order_prints_its_kitchen_ticket_on_creation(): void
    {
        $response = $this->actingAs($this->cashier)->postJson(route('admin.orders.store'), $this->payload())->assertCreated();

        $order = Order::query()->where('public_id', $response->json('data.order_id'))->sole();
        $ticket = PrintJob::query()->sole();
        $this->assertSame(["order:{$order->id}:kitchen", 'Retiro', null], [$ticket->dedupe_key, $ticket->payload['title'], $ticket->payload['customer']]);
        $this->assertSame([['name' => 'Coca-Cola', 'quantity' => 2]], $ticket->payload['lines']);
    }

    public function test_completing_and_confirming_a_quick_order_does_not_print_it_again(): void
    {
        $response = $this->actingAs($this->cashier)->postJson(route('admin.orders.store'), $this->payload())->assertCreated();
        $order = Order::query()->where('public_id', $response->json('data.order_id'))->sole();

        $this->actingAs($this->cashier)->get(route('admin.orders.show', $order))->assertOk();
        $this->actingAs($this->cashier)->get(route('admin.orders.edit', $order))->assertOk();
        $this->actingAs($this->cashier)->put(route('admin.orders.update', $order), [
            'items' => [['product_id' => $this->coke->id, 'quantity' => 2]], 'addons' => [], 'condition' => 'cooked',
            'delivery_type' => 'pickup', 'delivery_date' => $order->delivery_date?->toDateString(), 'delivery_period' => 'night',
            'customer' => ['name' => 'Ana', 'phone' => '3534 123456'], 'payment_status' => 'unpaid',
            'status' => 'waiting', 'expected_version' => $order->edit_version,
        ])->assertSessionHasNoErrors()->assertRedirect();

        $order->refresh();
        $this->assertSame([OrderStatus::Waiting, 'Ana'], [$order->status, $order->customer_name_snapshot]);
        $this->assertNotNull($order->customer_id);
        $this->assertSame(1, PrintJob::query()->count());
    }

    public function test_a_quick_order_takes_no_customer_schedule_or_address(): void
    {
        $this->actingAs($this->cashier)->postJson(route('admin.orders.store'), [
            ...$this->payload(),
            'customer' => ['name' => 'Ana'], 'customer_id' => 1,
            'delivery_date' => now()->toDateString(), 'delivery_period' => 'night', 'delivery_time' => '20:00', 'delivery_address' => 'Calle 1',
        ])->assertUnprocessable()->assertJsonValidationErrors(['customer', 'customer_id', 'delivery_date', 'delivery_period', 'delivery_time', 'delivery_address']);

        $this->actingAs($this->cashier)->postJson(route('admin.orders.store'), [...$this->payload(), 'delivery_type' => 'delivery'])
            ->assertUnprocessable()->assertJsonValidationErrors(['delivery_type']);

        $this->assertSame(0, Order::query()->count());
    }

    public function test_public_checkout_cannot_ask_for_a_quick_order(): void
    {
        $this->postJson('/checkout/orders', ['quick' => true, 'items' => []])->assertUnprocessable()->assertJsonValidationErrors(['quick']);
    }

    public function test_a_quick_order_is_not_announced_as_a_web_order(): void
    {
        $this->actingAs($this->cashier)->postJson(route('admin.orders.store'), $this->payload())->assertCreated();

        $this->actingAs($this->cashier)->getJson(route('admin.orders.web-pending'))
            ->assertOk()
            ->assertJsonPath('data.pending_count', 0)
            ->assertJsonPath('data.latest', null);
    }

    /** @return array<string, mixed> */
    private function payload(): array
    {
        return [
            'quick' => true, 'items' => [['product_id' => $this->coke->id, 'quantity' => 2]], 'addons' => [], 'condition' => 'cooked',
            'delivery_type' => 'pickup', 'notes' => null, 'payment_status' => 'unpaid',
        ];
    }
}
