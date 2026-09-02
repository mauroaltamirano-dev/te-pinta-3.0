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

    public function test_list_exposes_active_order_count_latest_order_date_and_effective_spend(): void
    {
        $admin = User::factory()->administrator()->create();
        $customer = $this->customer(['name' => 'Aggregate Customer']);
        $this->order($customer, 'TP-000101', 'delivered', 12000, ['delivery_date' => '2026-08-10']);
        $this->order($customer, 'TP-000102', 'cancelled', 5000, ['delivery_date' => '2026-08-12']);
        $deletedOrderId = $this->order($customer, 'TP-000103', 'delivered', 9000, ['delivery_date' => '2026-08-14']);
        DB::table('orders')->where('id', $deletedOrderId)->update(['deleted_at' => now()]);

        $this->actingAs($admin)->get(route('admin.customers.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('customers.data.0.orders_count', 2)
                ->where('customers.data.0.latest_order_date', '2026-08-12')
                ->where('customers.data.0.historical_spend', 12000));
    }

    public function test_administrator_can_sort_customers_by_whitelisted_aggregates_with_stable_ties(): void
    {
        $admin = User::factory()->administrator()->create();
        $first = $this->customer(['name' => 'Alpha']);
        $second = $this->customer(['name' => 'Beta']);
        $third = $this->customer(['name' => 'Gamma']);
        $withoutOrders = $this->customer(['name' => 'No orders']);
        $this->order($first, 'TP-000121', 'delivered', 1000);
        $this->order($second, 'TP-000122', 'delivered', 500);
        $this->order($second, 'TP-000123', 'cancelled', 9000);
        $this->order($third, 'TP-000124', 'delivered', 2000);

        $this->actingAs($admin)->get(route('admin.customers.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('filters.sort', 'name')
                ->where('filters.direction', 'asc')
                ->where('customers.data.0.name', 'Alpha')
                ->where('customers.data.1.name', 'Beta')
                ->where('customers.data.2.name', 'Gamma')
                ->where('customers.data.3.name', 'No orders'));

        $this->actingAs($admin)->get(route('admin.customers.index', [
            'sort' => 'name',
            'direction' => 'desc',
        ]))->assertInertia(fn (Assert $page) => $page
            ->where('filters.sort', 'name')
            ->where('filters.direction', 'desc')
            ->where('customers.data.0.name', 'No orders')
            ->where('customers.data.1.name', 'Gamma')
            ->where('customers.data.2.name', 'Beta')
            ->where('customers.data.3.name', 'Alpha'));

        $this->actingAs($admin)->get(route('admin.customers.index', [
            'sort' => 'orders_count',
            'direction' => 'desc',
        ]))->assertInertia(fn (Assert $page) => $page
            ->where('customers.data.0.name', 'Beta')
            ->where('customers.data.1.name', 'Alpha')
            ->where('customers.data.2.name', 'Gamma')
            ->where('customers.data.3.name', 'No orders'));

        $this->actingAs($admin)->get(route('admin.customers.index', [
            'sort' => 'historical_spend',
            'direction' => 'desc',
        ]))->assertInertia(fn (Assert $page) => $page
            ->where('customers.data.0.name', 'Gamma')
            ->where('customers.data.1.name', 'Alpha')
            ->where('customers.data.2.name', 'Beta')
            ->where('customers.data.3.name', 'No orders'));

        $this->actingAs($admin)->get(route('admin.customers.index', [
            'sort' => 'historical_spend',
            'direction' => 'asc',
        ]))->assertInertia(fn (Assert $page) => $page
            ->where('customers.data.0.id', $withoutOrders->id)
            ->where('customers.data.1.name', 'Beta')
            ->where('customers.data.2.name', 'Alpha')
            ->where('customers.data.3.name', 'Gamma'));
    }

    public function test_customer_sort_rejects_untrusted_columns_and_directions(): void
    {
        $admin = User::factory()->administrator()->create();
        $customer = $this->customer(['name' => 'Safe customer']);

        $this->actingAs($admin)->from(route('admin.customers.index'))->get(route('admin.customers.index', [
            'sort' => 'name desc; drop table customers',
            'direction' => 'sideways',
        ]))->assertRedirect(route('admin.customers.index'))
            ->assertSessionHasErrors(['sort', 'direction']);

        $this->assertDatabaseHas('customers', [
            'id' => $customer->id,
            'deleted_at' => null,
        ]);
    }

    public function test_historical_spend_sort_distinguishes_null_zero_and_equal_positive_totals(): void
    {
        $admin = User::factory()->administrator()->create();
        $withoutOrders = $this->customer(['name' => 'Without orders']);
        $zeroTotal = $this->customer(['name' => 'Zero total']);
        $firstEqualSpend = $this->customer(['name' => 'First equal spend']);
        $secondEqualSpend = $this->customer(['name' => 'Second equal spend']);
        $this->order($zeroTotal, 'TP-000131', 'delivered', 0);
        $this->order($firstEqualSpend, 'TP-000132', 'delivered', 1500);
        $this->order($secondEqualSpend, 'TP-000133', 'delivered', 1500);

        $this->actingAs($admin)->get(route('admin.customers.index', [
            'sort' => 'historical_spend',
            'direction' => 'asc',
        ]))->assertInertia(fn (Assert $page) => $page
            ->where('customers.data.0.id', $withoutOrders->id)
            ->where('customers.data.0.historical_spend', 0)
            ->where('customers.data.1.id', $zeroTotal->id)
            ->where('customers.data.1.historical_spend', 0)
            ->where('customers.data.2.id', $firstEqualSpend->id)
            ->where('customers.data.3.id', $secondEqualSpend->id));

        $this->actingAs($admin)->get(route('admin.customers.index', [
            'sort' => 'historical_spend',
            'direction' => 'desc',
        ]))->assertInertia(fn (Assert $page) => $page
            ->where('customers.data.0.id', $firstEqualSpend->id)
            ->where('customers.data.1.id', $secondEqualSpend->id)
            ->where('customers.data.2.id', $zeroTotal->id)
            ->where('customers.data.2.historical_spend', 0)
            ->where('customers.data.3.id', $withoutOrders->id)
            ->where('customers.data.3.historical_spend', 0));
    }

    public function test_list_read_model_query_count_does_not_grow_per_customer(): void
    {
        $admin = User::factory()->administrator()->create();
        $firstCustomer = $this->customer(['name' => 'Customer 1']);
        $this->order($firstCustomer, 'TP-000111', 'delivered', 12000);

        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->actingAs($admin)->get(route('admin.customers.index'))->assertOk();
        $singleCustomerQueryCount = count(DB::getQueryLog());
        DB::disableQueryLog();

        foreach (range(2, 5) as $number) {
            $customer = $this->customer(['name' => "Customer {$number}"]);
            $this->order($customer, sprintf('TP-%06d', 110 + $number), 'delivered', 12000);
        }

        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->actingAs($admin)->get(route('admin.customers.index'))->assertOk();
        $multipleCustomersQueryCount = count(DB::getQueryLog());
        DB::disableQueryLog();

        $this->assertSame($singleCustomerQueryCount, $multipleCustomersQueryCount);
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
                ->where('customer.orders_count', 2)
                ->where('customer.latest_order_date', '2026-08-12')
                ->has('customer.orders', 2)
                ->where('customer.orders.0.id', 'TP-000002')
                ->where('customer.orders.0.delivery_type', 'pickup')
                ->where('customer.orders.0.payment_status', 'unpaid')
                ->where('customer.orders.0.payment_method', null)
                ->where('customer.orders.1.id', 'TP-000001'));

        auth()->logout();
        $this->get(route('admin.customers.show', $customer))->assertRedirect(route('login'));
    }

    public function test_detail_hydrates_only_a_selected_active_order_owned_by_the_customer(): void
    {
        $admin = User::factory()->administrator()->create();
        $customer = $this->customer(['name' => 'Drawer Customer']);
        $otherCustomer = $this->customer(['name' => 'Other Customer']);
        $this->order($customer, 'TP-000201', 'waiting', 12000);
        $this->order($otherCustomer, 'TP-000202', 'waiting', 9000);
        $deletedOrderId = $this->order($customer, 'TP-000203', 'waiting', 7000);
        DB::table('orders')->where('id', $deletedOrderId)->update(['deleted_at' => now()]);

        $this->actingAs($admin)
            ->get(route('admin.customers.show', [$customer, 'order' => 'TP-000201']))
            ->assertInertia(fn (Assert $page) => $page
                ->where('selectedOrder.id', 'TP-000201')
                ->where('selectedOrder.customer.id', $customer->id)
                ->has('selectedOrder.items', 0)
                ->has('selectedOrder.toppings', 0)
                ->where('selectedOrderSummary', fn (string $summary): bool => str_contains($summary, 'TP-000201')));

        $this->actingAs($admin)
            ->get(route('admin.customers.show', [$customer, 'order' => 'TP-000202']))
            ->assertNotFound();
        $this->actingAs($admin)
            ->get(route('admin.customers.show', [$customer, 'order' => 'TP-000203']))
            ->assertNotFound();
        $this->actingAs($admin)
            ->get(route('admin.customers.show', [$customer, 'order' => 'invalid']))
            ->assertSessionHasErrors('order');
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

    /** @param array<string, mixed> $attributes */
    private function order(Customer $customer, string $publicId, string $status, int $total, array $attributes = []): int
    {
        return (int) DB::table('orders')->insertGetId(array_merge([
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
        ], $attributes));
    }
}
