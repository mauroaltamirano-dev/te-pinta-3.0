<?php

namespace Tests\Feature\Orders;

use App\Modules\Customers\Models\Customer;
use App\Modules\Identity\Models\User;
use App\Modules\Orders\Models\Order;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

final class OrderTrashRestorationTest extends TestCase
{
    use RefreshDatabase;

    public function test_administrator_can_list_and_restore_a_soft_deleted_order_by_public_id(): void
    {
        Log::spy();
        $admin = User::factory()->administrator()->create();
        $order = $this->order();
        $original = $order->only(['public_id', 'customer_name_snapshot', 'total']);

        $this->actingAs($admin)
            ->delete(route('admin.orders.destroy', $order))
            ->assertRedirect(route('admin.orders.index'));

        $this->assertSoftDeleted('orders', ['id' => $order->id]);

        $this->actingAs($admin)
            ->get(route('admin.orders.trash.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/orders/trash')
                ->has('orders.data', 1)
                ->where('orders.data.0.id', $order->public_id)
                ->where('orders.data.0.customer_name', 'Historical Customer')
                ->where('orders.data.0.total', 12500));

        $this->actingAs($admin)
            ->patch(route('admin.orders.trash.restore', $order->public_id))
            ->assertRedirect();

        $restored = Order::query()->findOrFail($order->id);
        $this->assertSame($original, $restored->only(array_keys($original)));
        $this->assertNull($restored->deleted_at);
        Log::assertLogged('info', fn (string $message, array $context): bool => $message === '[Orders] order.restored'
            && $context === [
                'module' => 'Orders',
                'order_id' => $order->public_id,
                'admin_id' => $admin->id,
            ]);
    }

    public function test_order_trash_requires_an_administrator_and_active_orders_cannot_be_restored(): void
    {
        $order = $this->order();

        $this->get(route('admin.orders.trash.index'))->assertRedirect(route('login'));
        $this->patch(route('admin.orders.trash.restore', $order->public_id))->assertRedirect(route('login'));

        $user = User::factory()->create();
        $this->actingAs($user)->get(route('admin.orders.trash.index'))->assertForbidden();
        $this->actingAs($user)->patch(route('admin.orders.trash.restore', $order->public_id))->assertForbidden();

        $admin = User::factory()->administrator()->create();
        $this->actingAs($admin)->patch(route('admin.orders.trash.restore', $order->public_id))->assertNotFound();
    }

    private function order(): Order
    {
        $customer = Customer::query()->create([
            'name' => 'Current Customer',
            'phone' => null,
            'phone_normalized' => null,
            'address' => null,
            'notes' => null,
        ]);

        $id = DB::table('orders')->insertGetId([
            'public_id' => 'TP-009901',
            'customer_id' => $customer->id,
            'source' => 'admin',
            'customer_name_snapshot' => 'Historical Customer',
            'customer_phone_snapshot' => null,
            'status' => 'waiting',
            'payment_status' => 'unpaid',
            'condition' => 'raw',
            'delivery_type' => 'pickup',
            'delivery_period' => 'night',
            'delivery_date' => '2026-08-14',
            'delivery_time' => null,
            'delivery_address_snapshot' => null,
            'notes' => null,
            'items_base_subtotal' => 12500,
            'cooking_surcharge' => 0,
            'items_subtotal' => 12500,
            'toppings_subtotal' => 0,
            'shipping_fee_snapshot' => 0,
            'combined_dozen_savings' => 0,
            'total' => 12500,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return Order::query()->findOrFail($id);
    }
}
