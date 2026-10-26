<?php

namespace Tests\Feature\Orders;

use App\Modules\Identity\Enums\Role;
use App\Modules\Identity\Models\User;
use App\Modules\Orders\Enums\OrderStatus;
use App\Modules\Orders\Models\Order;
use App\Modules\Products\Models\Category;
use App\Modules\Products\Models\Product;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/** A quick order may lack customer and period only while it is an admin pickup awaiting confirmation. */
final class QuickPendingOrderLifecycleTest extends TestCase
{
    use RefreshDatabase;

    private const CONFIRM_ERROR = 'Completá el nombre del cliente y la franja horaria antes de confirmar el pedido.';

    private User $cashier;

    private Product $coke;

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

    public function test_the_database_accepts_an_admin_pickup_without_customer_or_period_while_pending(): void
    {
        $order = $this->quickPendingOrder();

        $this->assertSame([null, null, null, OrderStatus::PendingConfirmation], [
            $order->customer_id, $order->customer_name_snapshot, $order->delivery_period, $order->status,
        ]);
    }

    /** @return iterable<string, array{array<string, mixed>}> */
    public static function invalidIncompleteOrders(): iterable
    {
        yield 'confirmed' => [['status' => 'waiting']];
        yield 'public checkout' => [['source' => 'public_checkout']];
        yield 'without delivery date' => [['delivery_date' => null]];
        yield 'name without customer' => [['customer_name_snapshot' => 'Ana']];
    }

    /** @param array<string, mixed> $override */
    #[DataProvider('invalidIncompleteOrders')]
    public function test_the_database_rejects_any_other_order_without_customer_or_period(array $override): void
    {
        $order = $this->quickPendingOrder();

        $this->expectException(QueryException::class);
        DB::table('orders')->where('id', $order->id)->update($override);
    }

    public function test_the_database_rejects_an_incomplete_delivery(): void
    {
        $order = $this->quickPendingOrder();

        $this->expectException(QueryException::class);
        DB::table('orders')->where('id', $order->id)->update(['delivery_type' => 'delivery', 'delivery_address_snapshot' => 'Calle 1']);
    }

    public function test_an_incomplete_order_cannot_be_confirmed_from_the_status_control(): void
    {
        $order = $this->quickPendingOrder();

        foreach ([OrderStatus::Waiting, OrderStatus::Prepared, OrderStatus::Delivered] as $status) {
            $this->actingAs($this->cashier)
                ->from(route('admin.orders.show', $order))
                ->patch(route('admin.orders.status.update', $order), ['status' => $status->value])
                ->assertRedirect(route('admin.orders.show', $order))
                ->assertSessionHasErrors(['status' => self::CONFIRM_ERROR]);
        }

        $this->assertSame(OrderStatus::PendingConfirmation, $order->refresh()->status);
        $this->assertSame(0, (int) $order->edit_version);
    }

    public function test_an_incomplete_order_can_be_cancelled(): void
    {
        $order = $this->quickPendingOrder();

        $this->actingAs($this->cashier)
            ->patch(route('admin.orders.status.update', $order), ['status' => OrderStatus::Cancelled->value])
            ->assertSessionHasNoErrors();

        $this->assertSame(OrderStatus::Cancelled, $order->refresh()->status);
    }

    public function test_a_cancelled_incomplete_order_cannot_be_reopened_without_its_data(): void
    {
        $order = $this->quickPendingOrder();
        DB::table('orders')->where('id', $order->id)->update(['status' => 'cancelled']);

        $this->actingAs($this->cashier)
            ->from(route('admin.orders.show', $order))
            ->patch(route('admin.orders.status.update', $order), ['status' => OrderStatus::Waiting->value])
            ->assertRedirect(route('admin.orders.show', $order))
            ->assertSessionHasErrors(['status' => self::CONFIRM_ERROR]);

        $this->assertSame(OrderStatus::Cancelled, $order->refresh()->status);
    }

    public function test_a_cancelled_incomplete_order_can_go_back_to_pending(): void
    {
        $order = $this->quickPendingOrder();
        DB::table('orders')->where('id', $order->id)->update(['status' => 'cancelled']);

        $this->actingAs($this->cashier)
            ->patch(route('admin.orders.status.update', $order), ['status' => OrderStatus::PendingConfirmation->value])
            ->assertSessionHasNoErrors();

        $this->assertSame(OrderStatus::PendingConfirmation, $order->refresh()->status);
    }

    public function test_a_complete_pending_order_is_still_confirmed_from_the_status_control(): void
    {
        $order = $this->pickupOrder();
        DB::table('orders')->where('id', $order->id)->update(['status' => 'pending_confirmation']);

        $this->actingAs($this->cashier)
            ->patch(route('admin.orders.status.update', $order), ['status' => OrderStatus::Waiting->value])
            ->assertSessionHasNoErrors();

        $this->assertSame(OrderStatus::Waiting, $order->refresh()->status);
    }

    private function pickupOrder(): Order
    {
        $response = $this->actingAs($this->cashier)->postJson(route('admin.orders.store'), [
            'items' => [['product_id' => $this->coke->id, 'quantity' => 2]], 'addons' => [], 'condition' => 'cooked',
            'delivery_type' => 'pickup', 'delivery_date' => now()->toDateString(), 'delivery_period' => 'night',
            'customer' => ['name' => 'Ana', 'phone' => null], 'payment_status' => 'unpaid',
        ])->assertCreated();

        return Order::query()->where('public_id', $response->json('data.order_id'))->sole();
    }

    /** Built through SQL so the lifecycle is proven without the quick-order endpoint. */
    private function quickPendingOrder(): Order
    {
        $order = $this->pickupOrder();
        DB::table('orders')->where('id', $order->id)->update([
            'status' => 'pending_confirmation', 'customer_id' => null, 'customer_name_snapshot' => null, 'delivery_period' => null, 'edit_version' => 0,
        ]);

        return $order->refresh();
    }
}
