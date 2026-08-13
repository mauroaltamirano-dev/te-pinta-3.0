<?php

namespace Tests\Feature\Orders;

use App\Modules\Customers\Models\Customer;
use App\Modules\Identity\Models\User;
use App\Modules\Orders\Enums\OrderSettingKey;
use App\Modules\Orders\Http\Resources\AdminOrderResource;
use App\Modules\Orders\Models\Order;
use App\Modules\Orders\Models\OrderSetting;
use App\Modules\Orders\Support\OrderSummaryFormatter;
use App\Modules\Products\Enums\PriceTier;
use App\Modules\Products\Models\Category;
use App\Modules\Products\Models\CookingSurcharge;
use App\Modules\Products\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

final class OrderCheckoutHttpTest extends TestCase
{
    use RefreshDatabase;

    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();
        $category = Category::query()->create(['name' => 'Empanadas', 'slug' => 'empanadas', 'sort_order' => 1, 'is_active' => true]);
        $this->product = Product::query()->create([
            'category_id' => $category->id, 'name' => 'Salteña', 'slug' => 'saltena',
            'unit_price' => 1600, 'half_dozen_price' => 9000, 'dozen_price' => 16000,
            'is_available' => true, 'sort_order' => 1,
        ]);
        CookingSurcharge::query()->insert([
            ['pricing_mode' => PriceTier::Unit->value, 'amount' => 200],
            ['pricing_mode' => PriceTier::HalfDozen->value, 'amount' => 1000],
            ['pricing_mode' => PriceTier::Dozen->value, 'amount' => 2000],
        ]);
        OrderSetting::query()->where('key', OrderSettingKey::WhatsappOrderPhone->value)->update(['string_value' => '5491112345678']);
    }

    public function test_public_checkout_is_backend_priced_persisted_and_safe(): void
    {
        $response = $this->postJson(route('public.orders.store'), $this->payload() + [
            'total' => 1, 'shipping_fee' => 0, 'payment_status' => 'paid', 'status' => 'delivered',
        ]);
        $response->assertUnprocessable()->assertJsonValidationErrors(['total', 'shipping_fee', 'payment_status', 'status']);

        $response = $this->postJson(route('public.orders.store'), $this->payload());
        $response->assertCreated()
            ->assertJsonPath('data.status', 'pending_confirmation')
            ->assertJsonPath('data.payment_status', 'unpaid')
            ->assertJsonPath('data.items_base_subtotal', 9000)
            ->assertJsonPath('data.cooking_surcharge', 0)
            ->assertJsonPath('data.total', 9000)
            ->assertJsonMissingPath('data.customer_id')
            ->assertJsonPath('data.order_id', 'TP-000001');
        $this->assertStringContainsString('TP-000001', $response->json('data.whatsapp_url'));

        $order = Order::query()->firstOrFail();
        $this->assertSame('Visitor name', $order->customer_name_snapshot);
        $this->assertSame(9000, (int) $order->total);
        $this->assertCount(1, $order->items);
    }

    public function test_cooked_checkout_persists_and_exposes_snapshot_safe_breakdown(): void
    {
        $payload = $this->payload();
        $payload['condition'] = 'cooked';

        $response = $this->postJson(route('public.orders.store'), $payload);
        $response->assertCreated()
            ->assertJsonPath('data.items_base_subtotal', 9000)
            ->assertJsonPath('data.cooking_surcharge', 1000)
            ->assertJsonPath('data.total', 10000);
        $this->assertStringContainsString('Empanadas base: $9.000', $response->json('data.summary'));
        $this->assertStringContainsString('Cocción: +$1.000', $response->json('data.summary'));

        $order = Order::query()->with(['items.compositions', 'toppings'])->firstOrFail();
        $item = $order->items->sole();
        $this->assertSame([9000, 1000, 10000], [
            (int) $order->items_base_subtotal,
            (int) $order->cooking_surcharge,
            (int) $order->total,
        ]);
        $this->assertSame([9000, 1000, 10000], [
            (int) $item->base_line_total,
            (int) $item->cooking_surcharge,
            (int) $item->line_total,
        ]);

        CookingSurcharge::query()->where('pricing_mode', PriceTier::HalfDozen->value)->update(['amount' => 5000]);
        $this->product->update(['half_dozen_price' => 50000]);

        $summary = app(OrderSummaryFormatter::class)->format($order->fresh(['items.compositions', 'toppings']));
        $this->assertStringContainsString('Empanadas base: $9.000', $summary);
        $this->assertStringContainsString('Cocción: +$1.000', $summary);
        $this->assertStringContainsString('Total: $10.000', $summary);

        $resource = (new AdminOrderResource($order->fresh(['items.compositions', 'toppings'])))->resolve();
        $this->assertSame(9000, $resource['items_base_subtotal']);
        $this->assertSame(1000, $resource['cooking_surcharge']);
        $this->assertSame(9000, $resource['items'][0]['base_subtotal']);
        $this->assertSame(1000, $resource['items'][0]['cooking_surcharge']);
    }

    public function test_cooked_combined_dozen_uses_its_own_raw_equivalent(): void
    {
        $secondProduct = Product::query()->create([
            'category_id' => $this->product->category_id,
            'name' => 'Humita',
            'slug' => 'humita',
            'unit_price' => 1600,
            'half_dozen_price' => 9000,
            'dozen_price' => 16000,
            'is_available' => true,
            'sort_order' => 2,
        ]);
        $payload = $this->payload();
        $payload['condition'] = 'cooked';
        $payload['items'] = [
            ['product_slug' => $this->product->slug, 'quantity' => 6],
            ['product_slug' => $secondProduct->slug, 'quantity' => 6],
        ];

        $response = $this->postJson(route('public.orders.store'), $payload);
        $response->assertCreated()
            ->assertJsonPath('data.items_base_subtotal', 16000)
            ->assertJsonPath('data.cooking_surcharge', 1500)
            ->assertJsonPath('data.total', 17500);
        $this->assertStringContainsString('Cocción: +$1.500', $response->json('data.summary'));

        $order = Order::query()->with('items')->firstOrFail();
        $this->assertSame(1500, (int) $order->items->sole()->cooking_surcharge);
    }

    public function test_legacy_orders_without_breakdown_are_not_rebuilt_from_current_prices(): void
    {
        $customer = Customer::query()->create(['name' => 'Historical customer']);
        $order = Order::query()->create([
            'customer_id' => $customer->id,
            'customer_name_snapshot' => $customer->name,
            'delivery_date' => now()->toDateString(),
            'items_subtotal' => 9000,
            'total' => 9000,
        ]);

        CookingSurcharge::query()->where('pricing_mode', PriceTier::HalfDozen->value)->update(['amount' => 5000]);
        $order->refresh();
        $summary = app(OrderSummaryFormatter::class)->format($order);

        $this->assertNull($order->items_base_subtotal);
        $this->assertNull($order->cooking_surcharge);
        $this->assertStringContainsString('Empanadas: $9.000', $summary);
        $this->assertStringNotContainsString('Cocción:', $summary);
    }

    public function test_public_checkout_rejects_internal_catalog_ids(): void
    {
        $payload = $this->payload();
        $payload['items'] = [['product_id' => $this->product->id, 'quantity' => 1]];
        $payload['addons'] = [['addon_id' => 1, 'quantity' => 1]];
        $this->postJson(route('public.orders.store'), $payload)->assertUnprocessable()
            ->assertJsonValidationErrors(['items.0', 'addons.0']);
    }

    public function test_matching_by_phone_never_overwrites_or_leaks_existing_customer_profile(): void
    {
        $customer = Customer::query()->create([
            'name' => 'Private profile', 'phone' => '+54 9 11 1234-5678', 'phone_normalized' => '5491112345678',
            'address' => 'Private address', 'notes' => 'Private notes',
        ]);

        $response = $this->postJson(route('public.orders.store'), $this->payload());
        $response->assertCreated()->assertJsonMissing(['Private profile', 'Private address', 'Private notes', $customer->id]);
        $this->assertDatabaseCount('customers', 1);
        $this->assertDatabaseHas('customers', ['id' => $customer->id, 'name' => 'Private profile', 'address' => 'Private address', 'notes' => 'Private notes']);
        $this->assertDatabaseHas('orders', ['customer_id' => $customer->id, 'customer_name_snapshot' => 'Visitor name']);
    }

    public function test_idempotency_replays_exact_checkout_and_rejects_changed_payload(): void
    {
        $first = $this->postJson(route('public.orders.store'), $this->payload())->assertCreated();
        $second = $this->postJson(route('public.orders.store'), $this->payload())->assertOk();
        $this->assertSame($first->json('data.order_id'), $second->json('data.order_id'));
        $this->assertDatabaseCount('orders', 1);

        $changed = $this->payload();
        $changed['items'][0]['quantity'] = 7;
        $this->postJson(route('public.orders.store'), $changed)
            ->assertUnprocessable()->assertJsonValidationErrors('idempotency_key');
        $this->assertDatabaseCount('orders', 1);
    }

    public function test_public_order_creation_is_rate_limited(): void
    {
        $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.42']);

        for ($attempt = 1; $attempt <= 5; $attempt++) {
            $payload = $this->payload();
            $payload['idempotency_key'] = 'checkout-rate-limit-'.str_pad((string) $attempt, 4, '0', STR_PAD_LEFT);
            $this->postJson(route('public.orders.store'), $payload)->assertCreated();
        }

        $payload = $this->payload();
        $payload['idempotency_key'] = 'checkout-rate-limit-blocked';
        $this->postJson(route('public.orders.store'), $payload)->assertTooManyRequests();
    }

    public function test_admin_boundaries_and_state_payment_and_soft_delete_operations(): void
    {
        $this->get(route('admin.orders.index'))->assertRedirect();
        $admin = User::factory()->administrator()->create();
        $customer = Customer::query()->create(['name' => 'POS customer']);
        $payload = $this->payload();
        unset($payload['idempotency_key']);
        $payload['items'] = [['product_id' => $this->product->id, 'quantity' => 6]];
        $payload['customer_id'] = $customer->id;
        unset($payload['customer']);
        $payload['payment_status'] = 'paid';

        $response = $this->actingAs($admin)->post(route('admin.orders.store'), $payload);
        $order = Order::query()->firstOrFail();
        $response->assertRedirect(route('admin.orders.show', $order->public_id));
        $this->assertSame('waiting', $order->status->value);
        $this->assertSame('paid', $order->payment_status->value);

        $this->actingAs($admin)->get(route('admin.orders.show', $order->public_id))->assertOk();
        $this->actingAs($admin)->get(route('admin.orders.index', ['order' => $order->public_id]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/orders/index')
                ->where('selectedOrder.id', $order->public_id)
                ->has('selectedOrder.items', 1)
                ->where('selectedOrderSummary', fn (string $summary) => str_contains($summary, $order->public_id)));
        $this->actingAs($admin)->get(route('admin.orders.show', $order->getKey()))->assertNotFound();
        $this->actingAs($admin)->patch(route('admin.orders.status.update', $order->public_id), ['status' => 'cancelled'])->assertRedirect();
        $this->actingAs($admin)->patch(route('admin.orders.payment-status.update', $order->public_id), ['payment_status' => 'unpaid'])->assertRedirect();
        $this->actingAs($admin)->delete(route('admin.orders.destroy', $order->public_id))->assertRedirect();
        $this->assertSoftDeleted($order);
    }

    public function test_admin_quick_create_does_not_silently_attach_duplicate_phone(): void
    {
        $admin = User::factory()->administrator()->create();
        Customer::query()->create(['name' => 'Existing', 'phone' => '11 2222', 'phone_normalized' => '112222']);
        $payload = $this->payload();
        unset($payload['idempotency_key']);
        $payload['items'] = [['product_id' => $this->product->id, 'quantity' => 1]];
        $payload['customer'] = ['name' => 'Different person', 'phone' => '11-2222'];

        $this->actingAs($admin)->post(route('admin.orders.store'), $payload)
            ->assertSessionHasErrors('customer.phone_normalized');
        $this->assertDatabaseCount('customers', 1);
        $this->assertDatabaseCount('orders', 0);
    }

    /** @return array<string, mixed> */
    private function payload(): array
    {
        return [
            'items' => [['product_slug' => $this->product->slug, 'quantity' => 6]],
            'addons' => [], 'condition' => 'raw', 'delivery_type' => 'pickup',
            'delivery_period' => 'night', 'delivery_date' => now()->toDateString(),
            'delivery_time' => null, 'delivery_address' => null, 'notes' => 'Ring the bell',
            'customer' => ['name' => 'Visitor name', 'phone' => '+54 9 11 1234-5678', 'address' => 'Visitor address'],
            'idempotency_key' => 'checkout-example-key-0001',
        ];
    }
}
