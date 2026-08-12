<?php

namespace Tests\Feature;

use App\Modules\Customers\Models\Customer;
use App\Modules\Identity\Models\User;
use App\Modules\Orders\Models\Order;
use App\Modules\Products\Models\Category;
use App\Modules\Products\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SecurityFoundationTest extends TestCase
{
    use RefreshDatabase;

    public function test_unauthenticated_users_cannot_execute_admin_endpoints(): void
    {
        $this->postJson(route('admin.orders.price-preview'), ['items' => []])
            ->assertUnauthorized();
    }

    public function test_changing_resource_ids_does_not_bypass_administrator_authorization(): void
    {
        $user = User::factory()->create();
        $customer = Customer::query()->create(['name' => 'Customer']);
        $order = Order::query()->forceCreate([
            'public_id' => 'TP-000001',
            'customer_id' => $customer->id,
            'delivery_date' => now()->toDateString(),
        ]);

        $this->actingAs($user)->get(route('admin.orders.show', $order))->assertForbidden();
        $this->actingAs($user)->get(route('admin.customers.show', $customer))->assertForbidden();
        $this->actingAs($user)
            ->patch(route('admin.orders.status.update', $order), ['status' => 'delivered'])
            ->assertForbidden();

        $this->assertSame('waiting', $order->refresh()->status->value);
    }

    public function test_server_prices_are_the_only_source_of_truth(): void
    {
        $admin = User::factory()->administrator()->create();
        $category = Category::query()->create(['name' => 'Classic', 'slug' => 'classic']);
        $product = Product::query()->create([
            'category_id' => $category->id,
            'name' => 'Beef',
            'slug' => 'beef',
            'unit_price' => 250,
            'half_dozen_price' => 1300,
            'dozen_price' => 2400,
            'is_available' => true,
        ]);

        $this->actingAs($admin)->postJson(route('admin.orders.price-preview'), [
            'items' => [[
                'product_id' => $product->id,
                'pricing_mode' => 'dozen',
                'quantity' => 2,
                'unit_price' => 1,
            ]],
        ])->assertOk()->assertJsonPath('total', 4800)->assertJsonPath('items.0.unit_price', 2400);
    }

    public function test_order_statuses_are_validated_on_the_server(): void
    {
        $admin = User::factory()->administrator()->create();
        $customer = Customer::query()->create(['name' => 'Customer']);
        $order = Order::query()->forceCreate([
            'public_id' => 'TP-000002',
            'customer_id' => $customer->id,
            'delivery_date' => now()->toDateString(),
        ]);

        $this->actingAs($admin)
            ->patch(route('admin.orders.status.update', $order), ['status' => 'refunded'])
            ->assertSessionHasErrors('status');

        $this->assertSame('waiting', $order->refresh()->status->value);
    }

    public function test_invalid_pricing_input_is_rejected(): void
    {
        $admin = User::factory()->administrator()->create();

        $this->actingAs($admin)->postJson(route('admin.orders.price-preview'), [
            'items' => [['product_id' => 999, 'pricing_mode' => 'free', 'quantity' => 0]],
        ])->assertUnprocessable()->assertJsonValidationErrors([
            'items.0.product_id', 'items.0.pricing_mode', 'items.0.quantity',
        ]);
    }

    public function test_public_pages_do_not_expose_internal_user_fields(): void
    {
        $response = $this->get(route('home'));

        $response->assertOk();
        $response->assertDontSee('is_admin');
        $response->assertDontSee('password');
        $response->assertDontSee('remember_token');
    }
}
