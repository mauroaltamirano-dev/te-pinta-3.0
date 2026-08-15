<?php

namespace Tests\Feature\Customers;

use App\Modules\Customers\Models\Customer;
use App\Modules\Identity\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

final class CustomerTrashRestorationTest extends TestCase
{
    use RefreshDatabase;

    public function test_administrator_can_restore_a_customer_without_changing_linked_orders(): void
    {
        Log::spy();
        $admin = User::factory()->administrator()->create();
        $customer = $this->customer();
        $orderId = $this->order($customer);

        $this->actingAs($admin)
            ->delete(route('admin.customers.destroy', $customer))
            ->assertRedirect(route('admin.customers.index'));

        $this->assertSoftDeleted('customers', ['id' => $customer->id]);
        $this->assertDatabaseHas('orders', ['id' => $orderId, 'deleted_at' => null]);

        $this->actingAs($admin)
            ->get(route('admin.customers.trash.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/customers/trash')
                ->has('customers.data', 1)
                ->where('customers.data.0.id', $customer->id)
                ->where('customers.data.0.name', 'Restorable Customer')
                ->where('customers.data.0.orders_count', 1));

        $this->actingAs($admin)
            ->patch(route('admin.customers.trash.restore', $customer->id))
            ->assertRedirect();

        $this->assertDatabaseHas('customers', [
            'id' => $customer->id,
            'name' => 'Restorable Customer',
            'deleted_at' => null,
        ]);
        $this->assertDatabaseHas('orders', [
            'id' => $orderId,
            'customer_name_snapshot' => 'Historical Customer Name',
            'total' => 7800,
            'deleted_at' => null,
        ]);
        Log::assertLogged('info', fn (string $message, array $context): bool => $message === '[Customers] customer.restored'
            && $context === [
                'module' => 'Customers',
                'customer_id' => $customer->id,
                'admin_id' => $admin->id,
            ]);
    }

    public function test_customer_trash_requires_an_administrator_and_active_customers_cannot_be_restored(): void
    {
        $customer = $this->customer();

        $this->get(route('admin.customers.trash.index'))->assertRedirect(route('login'));
        $this->patch(route('admin.customers.trash.restore', $customer->id))->assertRedirect(route('login'));

        $user = User::factory()->create();
        $this->actingAs($user)->get(route('admin.customers.trash.index'))->assertForbidden();
        $this->actingAs($user)->patch(route('admin.customers.trash.restore', $customer->id))->assertForbidden();

        $admin = User::factory()->administrator()->create();
        $this->actingAs($admin)->patch(route('admin.customers.trash.restore', $customer->id))->assertNotFound();
    }

    public function test_restoration_is_rejected_without_mutation_when_an_active_customer_uses_the_same_phone(): void
    {
        $admin = User::factory()->administrator()->create();
        $customer = $this->customer();
        $customer->delete();
        Customer::query()->create([
            'name' => 'Current Phone Owner',
            'phone' => '+54 (381) 555-9999',
            'phone_normalized' => $customer->phone_normalized,
            'address' => null,
            'notes' => null,
        ]);

        $this->actingAs($admin)
            ->patch(route('admin.customers.trash.restore', $customer->id))
            ->assertSessionHasErrors([
                'restore' => 'No se puede restaurar el cliente porque su teléfono está asignado a otro cliente activo. Modificá ese cliente y volvé a intentarlo.',
            ]);

        $this->assertSoftDeleted('customers', ['id' => $customer->id]);
        $this->assertSame(
            '+54 381 555 9999',
            Customer::withTrashed()->findOrFail($customer->id)->phone,
        );
    }

    private function customer(): Customer
    {
        return Customer::query()->create([
            'name' => 'Restorable Customer',
            'phone' => '+54 381 555 9999',
            'phone_normalized' => '543815559999',
            'address' => 'Private address',
            'notes' => 'Private note',
        ]);
    }

    private function order(Customer $customer): int
    {
        return (int) DB::table('orders')->insertGetId([
            'public_id' => 'TP-009902',
            'customer_id' => $customer->id,
            'source' => 'admin',
            'customer_name_snapshot' => 'Historical Customer Name',
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
            'items_base_subtotal' => 7800,
            'cooking_surcharge' => 0,
            'items_subtotal' => 7800,
            'toppings_subtotal' => 0,
            'shipping_fee_snapshot' => 0,
            'combined_dozen_savings' => 0,
            'total' => 7800,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
