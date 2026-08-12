<?php

namespace Tests\Feature\Customers;

use App\Modules\Customers\Models\Customer;
use App\Modules\Identity\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

final class CustomerManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_and_non_administrators_cannot_access_customer_crud(): void
    {
        $customer = $this->customer();

        $this->get(route('admin.customers.index'))->assertRedirect(route('login'));
        $this->post(route('admin.customers.store'), ['name' => 'Visitor'])->assertRedirect(route('login'));
        $this->get(route('admin.customers.show', $customer))->assertRedirect(route('login'));
        $this->put(route('admin.customers.update', $customer), ['name' => 'Visitor'])->assertRedirect(route('login'));
        $this->delete(route('admin.customers.destroy', $customer))->assertRedirect(route('login'));

        $user = User::factory()->create();
        $this->actingAs($user)->get(route('admin.customers.index'))->assertForbidden();
        $this->actingAs($user)->post(route('admin.customers.store'), ['name' => 'User'])->assertForbidden();
        $this->actingAs($user)->get(route('admin.customers.show', $customer))->assertForbidden();
        $this->actingAs($user)->put(route('admin.customers.update', $customer), ['name' => 'User'])->assertForbidden();
        $this->actingAs($user)->delete(route('admin.customers.destroy', $customer))->assertForbidden();
    }

    public function test_name_is_required_but_duplicate_names_and_missing_phones_are_allowed(): void
    {
        $admin = User::factory()->administrator()->create();

        $this->actingAs($admin)->post(route('admin.customers.store'), [
            'name' => '',
        ])->assertSessionHasErrors('name');

        $this->actingAs($admin)->post(route('admin.customers.store'), ['name' => 'Juan Pérez'])->assertRedirect();
        $this->actingAs($admin)->post(route('admin.customers.store'), ['name' => 'Juan Pérez'])->assertRedirect();

        $this->assertDatabaseCount('customers', 2);
        $this->assertSame(2, Customer::query()->where('name', 'Juan Pérez')->whereNull('phone')->count());
    }

    public function test_administrator_can_create_update_and_soft_delete_a_customer_without_logging_private_data(): void
    {
        Log::spy();
        $admin = User::factory()->administrator()->create();

        $this->actingAs($admin)->post(route('admin.customers.store'), [
            'name' => '  Ana Díaz ',
            'phone' => ' +54 (381) 555-0101 ',
            'address' => '  San Martín 123 ',
            'notes' => '  Prefers calls after 18:00 ',
        ])->assertRedirect();

        $customer = Customer::query()->sole();
        $this->assertSame('Ana Díaz', $customer->name);
        $this->assertSame('+54 (381) 555-0101', $customer->phone);
        $this->assertSame('543815550101', $customer->phone_normalized);
        $this->assertSame('San Martín 123', $customer->address);
        $this->assertSame('Prefers calls after 18:00', $customer->notes);
        Log::assertLogged('info', fn (string $message, array $context): bool => $message === '[Customers] customer.created'
            && $context['customer_id'] === $customer->id
            && $context['admin_id'] === $admin->id
            && ! array_key_exists('phone', $context)
            && ! array_key_exists('address', $context)
            && ! array_key_exists('notes', $context));

        $this->actingAs($admin)->put(route('admin.customers.update', $customer), [
            'name' => 'Ana Díaz',
            'phone' => null,
            'address' => '',
            'notes' => null,
        ])->assertRedirect();

        $this->assertNull($customer->refresh()->phone);
        $this->assertNull($customer->phone_normalized);
        $this->assertNull($customer->address);

        $this->actingAs($admin)->delete(route('admin.customers.destroy', $customer))->assertRedirect(route('admin.customers.index'));
        $this->assertSoftDeleted('customers', ['id' => $customer->id]);
    }

    public function test_active_normalized_phone_is_unique_but_can_be_reused_after_soft_delete(): void
    {
        $admin = User::factory()->administrator()->create();
        $existing = $this->customer([
            'phone' => '+54 381 555-0199',
            'phone_normalized' => '543815550199',
        ]);

        $this->actingAs($admin)->post(route('admin.customers.store'), [
            'name' => 'Collision',
            'phone' => '+54 (381) 555 0199',
        ])->assertSessionHasErrors('phone_normalized');

        $existing->delete();

        $this->actingAs($admin)->post(route('admin.customers.store'), [
            'name' => 'New owner',
            'phone' => '+54 (381) 555 0199',
        ])->assertRedirect();

        $this->assertSame(1, Customer::query()->where('phone_normalized', '543815550199')->count());
    }

    public function test_administrator_can_search_by_name_and_formatted_phone(): void
    {
        $admin = User::factory()->administrator()->create();
        $this->customer(['name' => 'María Gómez', 'phone' => '381 555-0100', 'phone_normalized' => '3815550100']);
        $this->customer(['name' => 'Pedro Ruiz', 'phone' => '381 555-0200', 'phone_normalized' => '3815550200']);

        $this->actingAs($admin)->get(route('admin.customers.index', ['search' => 'maría']))
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/customers/index')
                ->has('customers.data', 1)
                ->where('customers.data.0.name', 'María Gómez'));

        $this->actingAs($admin)->get(route('admin.customers.index', ['search' => '(381) 555-0200']))
            ->assertInertia(fn (Assert $page) => $page
                ->has('customers.data', 1)
                ->where('customers.data.0.name', 'Pedro Ruiz'));
    }

    public function test_detail_exposes_private_notes_only_to_admin_and_lists_active_order_history(): void
    {
        $admin = User::factory()->administrator()->create();
        $customer = $this->customer(['notes' => 'Internal allergy note']);
        $this->order($customer, 'TP-000001', 'delivered', 12000);
        $this->order($customer, 'TP-000002', 'cancelled', 5000);
        $deletedOrderId = $this->order($customer, 'TP-000003', 'delivered', 9000);
        DB::table('orders')->where('id', $deletedOrderId)->update(['deleted_at' => now()]);

        $this->actingAs($admin)->get(route('admin.customers.show', $customer))
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/customers/show')
                ->where('customer.notes', 'Internal allergy note')
                ->where('customer.historical_spend', 12000)
                ->has('customer.orders', 2)
                ->where('customer.orders.0.id', 'TP-000002')
                ->where('customer.orders.1.id', 'TP-000001'));

        auth()->logout();
        $this->get(route('admin.customers.show', $customer))->assertRedirect(route('login'));
    }

    /** @param array<string, mixed> $attributes */
    private function customer(array $attributes = []): Customer
    {
        return Customer::query()->create(array_merge([
            'name' => 'Test Customer',
            'phone' => null,
            'phone_normalized' => null,
            'address' => null,
            'notes' => null,
        ], $attributes));
    }

    private function order(Customer $customer, string $publicId, string $status, int $total): int
    {
        return (int) DB::table('orders')->insertGetId([
            'public_id' => $publicId,
            'customer_id' => $customer->id,
            'source' => 'admin',
            'customer_name_snapshot' => $customer->name,
            'customer_phone_snapshot' => $customer->phone,
            'status' => $status,
            'payment_status' => 'unpaid',
            'condition' => 'raw',
            'delivery_type' => 'pickup',
            'delivery_period' => 'night',
            'delivery_date' => '2026-08-12',
            'delivery_time' => null,
            'delivery_address_snapshot' => null,
            'notes' => null,
            'items_subtotal' => $total,
            'toppings_subtotal' => 0,
            'shipping_fee_snapshot' => 0,
            'combined_dozen_savings' => 0,
            'total' => $total,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
