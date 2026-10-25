<?php

namespace Tests\Feature\Orders;

use App\Modules\Customers\Models\Customer;
use App\Modules\Identity\Models\User;
use App\Modules\Management\Models\ManagementOrderPayment;
use App\Modules\Management\Models\Wallet;
use App\Modules\Orders\Enums\OrderSettingKey;
use App\Modules\Orders\Http\Resources\AdminOrderResource;
use App\Modules\Orders\Models\Order;
use App\Modules\Orders\Models\OrderSetting;
use App\Modules\Orders\Support\OrderSummaryFormatter;
use App\Modules\Products\Models\Category;
use App\Modules\Products\Models\Product;
use Carbon\CarbonImmutable;
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
        Wallet::query()->create(['name' => 'Order receipts', 'is_order_payment_default' => true]);
        $category = Category::query()->create(['name' => 'Empanadas', 'slug' => 'empanadas', 'sort_order' => 1, 'is_active' => true]);
        $this->product = Product::query()->create([
            'category_id' => $category->id, 'name' => 'Salteña', 'slug' => 'saltena',
            'unit_price' => 1800, 'half_dozen_price' => 10000, 'dozen_price' => 18000,
            'is_available' => true, 'sort_order' => 1,
        ]);
        OrderSetting::query()->where('key', OrderSettingKey::WhatsappOrderPhone->value)->update(['string_value' => '5491112345678']);
    }

    public function test_public_checkout_is_backend_priced_persisted_and_safe(): void
    {
        $response = $this->postJson(route('public.orders.store'), $this->payload() + [
            'total' => 1, 'shipping_fee' => 0, 'payment_status' => 'paid', 'payment_method' => 'cash',
            'discount_type' => 'percentage', 'discount_value' => 100, 'discount_amount' => 9000, 'status' => 'delivered',
        ]);
        $response->assertUnprocessable()->assertJsonValidationErrors([
            'total', 'shipping_fee', 'payment_status', 'payment_method', 'discount_type', 'discount_value', 'discount_amount', 'status',
        ]);

        $response = $this->postJson(route('public.orders.store'), $this->payload());
        $response->assertCreated()
            ->assertJsonPath('data.status', 'pending_confirmation')
            ->assertJsonPath('data.payment_status', 'unpaid')
            ->assertJsonPath('data.items_base_subtotal', null)
            ->assertJsonPath('data.cooking_surcharge', null)
            ->assertJsonPath('data.items_cooked_subtotal', 10800)
            ->assertJsonPath('data.raw_discount', 1200)
            ->assertJsonPath('data.total', 9600)
            ->assertJsonMissingPath('data.customer_id');
        $this->assertMatchesRegularExpression('/^TP-\d{6}$/', $response->json('data.order_id'));
        $this->assertStringContainsString($response->json('data.order_id'), $response->json('data.whatsapp_url'));

        $order = Order::query()->firstOrFail();
        $this->assertSame('Visitor name', $order->customer_name_snapshot);
        $this->assertSame(9600, (int) $order->total);
        $this->assertCount(1, $order->items);
    }

    public function test_public_checkout_requotes_unit_products_and_preserves_the_persisted_snapshot(): void
    {
        $unitProduct = Product::query()->create([
            'category_id' => $this->product->category_id,
            'pricing_family' => 'unit',
            'name' => 'Soda',
            'slug' => 'soda',
            'unit_price' => 2_500,
            'half_dozen_price' => 2_500,
            'dozen_price' => 2_500,
            'is_available' => true,
            'sort_order' => 2,
        ]);
        $payload = $this->payload();
        $payload['items'] = [['product_slug' => $unitProduct->slug, 'quantity' => 3]];
        $this->postJson(route('public.orders.store'), $payload)
            ->assertCreated()
            ->assertJsonPath('data.total', 7_500)
            ->assertJsonPath('data.raw_discount', 0);

        $order = Order::query()->with('items.compositions')->sole();
        $item = $order->items->sole();
        $this->assertSame(['unit', 3, 3, 2_500, 7_500, 0], [
            $item->pricing_mode->value,
            $item->quantity,
            $item->units_count,
            $item->unit_price,
            $item->line_total,
            $item->raw_discount,
        ]);

        $unitProduct->update([
            'pricing_family' => 'empanada',
            'unit_price' => 99_999,
            'half_dozen_price' => 500_000,
            'dozen_price' => 900_000,
        ]);
        OrderSetting::query()->where('key', OrderSettingKey::RawDiscountPercent->value)->update(['integer_value' => 50]);

        $resource = (new AdminOrderResource($order->fresh(['items.compositions', 'toppings'])))->resolve();
        $this->assertSame(7_500, $resource['total']);
        $this->assertSame(2_500, $resource['items'][0]['unit_price']);
        $this->assertSame(0, $resource['items'][0]['raw_discount']);
        $this->assertSame(['unit', 3, 2_500, 7_500], [
            $resource['items'][0]['pricing_mode'],
            $resource['items'][0]['quantity'],
            $resource['items'][0]['unit_price'],
            $resource['items'][0]['subtotal'],
        ]);
    }

    public function test_public_checkout_order_is_visible_to_an_administrator(): void
    {
        $publicOrderId = $this->postJson(route('public.orders.store'), $this->payload())
            ->assertCreated()
            ->json('data.order_id');
        $admin = User::factory()->administrator()->create();

        $this->actingAs($admin)->get(route('admin.orders.index', ['order' => $publicOrderId]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/orders/index')
                ->where('orders.data.0.id', $publicOrderId)
                ->where('selectedOrder.id', $publicOrderId)
                ->where('selectedOrder.status', 'pending_confirmation'));
        $this->actingAs($admin)->get(route('admin.orders.show', $publicOrderId))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/orders/show')
                ->where('order.id', $publicOrderId));
    }

    public function test_public_checkout_uses_the_orders_business_date_while_admin_can_register_past_orders(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-08-14 00:30:00 UTC'));

        $this->getJson(route('public.orders.options'))
            ->assertOk()
            ->assertJsonPath('data.today', '2026-08-13');

        $publicPayload = $this->payload();
        $publicPayload['delivery_date'] = '2026-08-13';
        $this->postJson(route('public.orders.store'), $publicPayload)->assertCreated();

        $publicPayload['delivery_date'] = '2026-08-12';
        $publicPayload['idempotency_key'] = 'checkout-business-date-old';
        $this->postJson(route('public.orders.store'), $publicPayload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('delivery_date')
            ->assertJsonPath('errors.delivery_date.0', 'La fecha de entrega debe ser igual o posterior al 13/08/2026.');

        $adminPayload = $this->payload();
        unset($adminPayload['idempotency_key'], $adminPayload['customer']);
        $adminPayload['items'] = [['product_id' => $this->product->id, 'quantity' => 1]];
        $adminPayload['customer_id'] = Customer::query()->create(['name' => 'Business date customer'])->id;
        $adminPayload['delivery_date'] = '2026-08-12';
        $this->actingAs(User::factory()->administrator()->create())
            ->postJson(route('admin.orders.store'), $adminPayload)->assertCreated();

        $this->assertDatabaseHas('orders', ['delivery_date' => '2026-08-12']);
    }

    public function test_cooked_checkout_persists_and_exposes_snapshot_safe_breakdown(): void
    {
        $payload = $this->payload();
        $payload['condition'] = 'cooked';

        $response = $this->postJson(route('public.orders.store'), $payload);
        $response->assertCreated()
            ->assertJsonPath('data.items_base_subtotal', null)
            ->assertJsonPath('data.cooking_surcharge', null)
            ->assertJsonPath('data.items_cooked_subtotal', 10800)
            ->assertJsonPath('data.raw_discount', 0)
            ->assertJsonPath('data.total', 10800);
        $this->assertStringContainsString('Productos: $10.800', $response->json('data.summary'));
        $this->assertStringNotContainsString('Descuento por preparación cruda', $response->json('data.summary'));

        $order = Order::query()->with(['items.compositions', 'toppings'])->firstOrFail();
        $item = $order->items->sole();
        $this->assertSame([10800, 0, 10800], [
            (int) $order->items_cooked_subtotal,
            (int) $order->raw_discount,
            (int) $order->total,
        ]);
        $this->assertSame([10800, 0, 10800], [
            (int) $item->cooked_line_total,
            (int) $item->raw_discount,
            (int) $item->line_total,
        ]);

        OrderSetting::query()->where('key', OrderSettingKey::RawDiscountPercent->value)->update(['integer_value' => 50]);
        $this->product->update(['half_dozen_price' => 50000]);

        $summary = app(OrderSummaryFormatter::class)->format($order->fresh(['items.compositions', 'toppings']));
        $this->assertStringContainsString('Productos: $10.800', $summary);
        $this->assertStringNotContainsString('Descuento por preparación cruda', $summary);
        $this->assertStringContainsString('Total: $10.800', $summary);

        $resource = (new AdminOrderResource($order->fresh(['items.compositions', 'toppings'])))->resolve();
        $this->assertNull($resource['items_base_subtotal']);
        $this->assertNull($resource['cooking_surcharge']);
        $this->assertSame(10800, $resource['items_cooked_subtotal']);
        $this->assertSame(0, $resource['raw_discount']);
        $this->assertSame(10800, $resource['items'][0]['cooked_subtotal']);
        $this->assertSame(0, $resource['items'][0]['raw_discount']);
    }

    public function test_public_checkout_sells_twelve_cooked_mixed_flavors_as_a_combined_dozen(): void
    {
        $secondProduct = Product::query()->create([
            'category_id' => $this->product->category_id,
            'name' => 'Humita',
            'slug' => 'humita',
            'unit_price' => 1800,
            'half_dozen_price' => 10000,
            'dozen_price' => 18000,
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
            // 12 × 1800 = 21600 less the 20% combined discount = 17280 → 17300.
            ->assertJsonPath('data.items_cooked_subtotal', 17280)
            ->assertJsonPath('data.raw_discount', 0)
            ->assertJsonPath('data.total', 17300);
        $this->assertStringNotContainsString('Descuento por preparación cruda', $response->json('data.summary'));

        $order = Order::query()->with('items')->firstOrFail();
        $this->assertSame([['combined_dozen', 0, 4320]], $order->items->map(fn ($item) => [$item->pricing_mode->value, (int) $item->raw_discount, (int) $item->savings])->all());
        $this->assertSame(4320, (int) $order->combined_dozen_savings);
    }

    public function test_admin_summary_formats_a_structured_combined_order_for_whatsapp_copy(): void
    {
        $customer = Customer::query()->create(['name' => 'Agostina Salomón']);
        $order = Order::query()->forceCreate([
            'public_id' => 'TP-000015',
            'customer_id' => $customer->id,
            'source' => 'admin',
            'customer_name_snapshot' => $customer->name,
            'customer_phone_snapshot' => '11 5555-9999',
            'status' => 'prepared',
            'payment_status' => 'unpaid',
            'condition' => 'cooked',
            'delivery_type' => 'delivery',
            'delivery_period' => 'night',
            'delivery_date' => '2026-08-14',
            'delivery_address_snapshot' => 'Dirección privada que no se copia',
            'notes' => 'Sin cebolla',
            'items_base_subtotal' => 18800,
            'cooking_surcharge' => 1900,
            'items_subtotal' => 20700,
            'toppings_subtotal' => 0,
            'shipping_fee_snapshot' => 2000,
            'combined_dozen_savings' => 3500,
            'total' => 22700,
        ]);
        $order->items()->create([
            'product_id' => $this->product->id,
            'product_name' => 'Jamón y Queso',
            'pricing_mode' => 'unit',
            'quantity' => 2,
            'units_count' => 2,
            'unit_price' => 1600,
            'line_total' => 3200,
            'base_unit_price' => 1450,
            'base_line_total' => 2900,
            'cooking_surcharge' => 300,
            'regular_price_total' => 3200,
            'savings' => 0,
        ]);
        $combined = $order->items()->create([
            'product_name' => 'Docena combinada',
            'pricing_mode' => 'combined_dozen',
            'quantity' => 1,
            'units_count' => 12,
            'unit_price' => 17500,
            'line_total' => 17500,
            'base_unit_price' => 16000,
            'base_line_total' => 16000,
            'cooking_surcharge' => 1500,
            'regular_price_total' => 21000,
            'savings' => 3500,
        ]);
        $combined->compositions()->createMany([
            ['product_id' => $this->product->id, 'product_name' => 'Salteñas', 'quantity' => 4],
            ['product_id' => $this->product->id, 'product_name' => 'Pollo y portobellos', 'quantity' => 2],
            ['product_id' => $this->product->id, 'product_name' => 'Matambre y verduras asadas', 'quantity' => 2],
            ['product_id' => $this->product->id, 'product_name' => 'Jamón y Queso', 'quantity' => 4],
        ]);

        $summary = app(OrderSummaryFormatter::class)->formatAdmin($order);

        $this->assertSame(<<<'TEXT'
🧾 *PEDIDO TP-000015*

👤 *Cliente:* Agostina Salomón
🛵 *Modalidad:* Envío

*DETALLE DEL PEDIDO*

• 2 × Jamón y Queso

• 1 × Docena combinada
└ 4 Salteñas
└ 2 Pollo y portobellos
└ 2 Matambre y verduras asadas
└ 4 Jamón y Queso

*RESUMEN*

Productos: $24.200
Descuento: -$3.500
Envío: $2.000

💰 *TOTAL: $22.700*

📝 *Nota:* Sin cebolla
TEXT, $summary);
        $this->assertStringNotContainsString('11 5555-9999', $summary);
        $this->assertStringNotContainsString('Dirección privada que no se copia', $summary);
    }

    public function test_admin_summary_omits_optional_cooking_savings_addons_time_and_note(): void
    {
        $customer = Customer::query()->create(['name' => 'Paisa']);
        $order = Order::query()->forceCreate([
            'public_id' => 'TP-000014',
            'customer_id' => $customer->id,
            'source' => 'admin',
            'customer_name_snapshot' => $customer->name,
            'status' => 'waiting',
            'payment_status' => 'unpaid',
            'condition' => 'raw',
            'delivery_type' => 'pickup',
            'delivery_period' => 'midday',
            'delivery_date' => '2026-08-14',
            'items_base_subtotal' => 1600,
            'cooking_surcharge' => 0,
            'items_subtotal' => 1600,
            'toppings_subtotal' => 0,
            'shipping_fee_snapshot' => 0,
            'combined_dozen_savings' => 0,
            'total' => 1600,
        ]);
        $order->items()->create([
            'product_id' => $this->product->id,
            'product_name' => 'Salteña',
            'pricing_mode' => 'unit',
            'quantity' => 1,
            'units_count' => 1,
            'unit_price' => 1600,
            'line_total' => 1600,
            'base_unit_price' => 1600,
            'base_line_total' => 1600,
            'cooking_surcharge' => 0,
            'regular_price_total' => 1600,
            'savings' => 0,
        ]);

        $summary = app(OrderSummaryFormatter::class)->formatAdmin($order);

        $this->assertStringNotContainsString('Entrega', $summary);
        $this->assertStringNotContainsString('Condición', $summary);
        $this->assertStringContainsString('🥟 *Crudas*', $summary);
        $this->assertStringNotContainsString('Cocción:', $summary);
        $this->assertStringNotContainsString('Descuento', $summary);
        $this->assertStringNotContainsString('Envío:', $summary);
        $this->assertStringNotContainsString('Toppings:', $summary);
        $this->assertStringNotContainsString('*ADICIONALES*', $summary);
        $this->assertStringNotContainsString('📝 *Nota:*', $summary);
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

        OrderSetting::query()->where('key', OrderSettingKey::RawDiscountPercent->value)->update(['integer_value' => 50]);
        $order->refresh();
        $summary = app(OrderSummaryFormatter::class)->format($order);

        $this->assertNull($order->items_base_subtotal);
        $this->assertNull($order->cooking_surcharge);
        $this->assertStringContainsString('Productos: $9.000', $summary);
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
        $payload['payment_method'] = 'cash';

        $response = $this->actingAs($admin)->post(route('admin.orders.store'), $payload);
        $order = Order::query()->firstOrFail();
        $response->assertRedirect(route('admin.orders.show', $order->public_id));
        $this->assertSame('waiting', $order->status->value);
        $this->assertSame('paid', $order->payment_status->value);
        $this->assertSame('cash', $order->payment_method->value);
        $payment = ManagementOrderPayment::query()->sole();
        $this->assertSame($order->id, $payment->order_id);
        $this->assertSame(9600, (int) $payment->amount);

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

    public function test_admin_json_store_creates_exactly_one_waiting_order_with_selected_payment_status(): void
    {
        $admin = User::factory()->administrator()->create();
        $customer = Customer::query()->create(['name' => 'Quick POS customer']);
        $payload = $this->payload();
        unset($payload['idempotency_key'], $payload['customer']);
        $payload['items'] = [['product_id' => $this->product->id, 'quantity' => 6]];
        $payload['customer_id'] = $customer->id;
        $payload['payment_status'] = 'paid';
        $payload['payment_method'] = 'bank_transfer';

        $response = $this->actingAs($admin)->postJson(route('admin.orders.store'), $payload);

        $response->assertCreated()
            ->assertJsonPath('data.status', 'waiting')
            ->assertJsonPath('data.payment_status', 'paid')
            ->assertJsonPath('data.total', 9600)
            ->assertJsonPath('data.summary', fn (string $summary): bool => str_contains($summary, '🧾 *PEDIDO TP-'));
        $this->assertDatabaseCount('orders', 1);

        $order = Order::query()->sole();
        $this->assertMatchesRegularExpression('/^TP-\d{6}$/', $response->json('data.order_id'));
        $this->assertSame($order->public_id, $response->json('data.order_id'));
        $this->assertSame('waiting', $order->status->value);
        $this->assertSame('paid', $order->payment_status->value);
        $this->assertSame('bank_transfer', $order->payment_method->value);
        $this->assertSame($order->id, ManagementOrderPayment::query()->sole()->order_id);
    }

    public function test_admin_preview_and_create_apply_only_backend_calculated_discount(): void
    {
        $admin = User::factory()->administrator()->create();
        $customer = Customer::query()->create(['name' => 'Discount customer']);
        $payload = $this->payload();
        unset($payload['idempotency_key'], $payload['customer']);
        $payload['items'] = [['product_id' => $this->product->id, 'quantity' => 6]];
        $payload['customer_id'] = $customer->id;
        $payload['payment_status'] = 'paid';
        $payload['payment_method'] = 'debit_card';
        $payload['discount_type'] = 'percentage';
        $payload['discount_value'] = 10;

        $this->actingAs($admin)->postJson(route('admin.orders.price-preview'), [
            'items' => $payload['items'],
            'addons' => [],
            'condition' => 'raw',
            'delivery_type' => 'pickup',
            'discount_type' => 'percentage',
            'discount_value' => 10,
        ])->assertOk()
            ->assertJsonPath('data.discountable_subtotal', 9600)
            ->assertJsonPath('data.discount_amount', 960)
            ->assertJsonPath('data.total', 8600);

        $tampered = $payload + ['discount_amount' => 8999, 'total' => 1];
        $this->actingAs($admin)->post(route('admin.orders.store'), $tampered)
            ->assertSessionHasErrors(['discount_amount', 'total']);
        $this->assertDatabaseCount('orders', 0);

        $this->actingAs($admin)->postJson(route('admin.orders.store'), $payload)
            ->assertCreated()
            ->assertJsonPath('data.total', 8600)
            ->assertJsonPath('data.summary', fn (string $summary): bool => str_contains($summary, "Productos: \$10.800\nDescuento: -\$2.200\n\n💰 *TOTAL: \$8.600*"));
        $order = Order::query()->sole();
        $this->assertSame('percentage', $order->discount_type->value);
        $this->assertSame(10, $order->discount_value);
        $this->assertSame(960, (int) $order->discount_amount);
        $this->assertSame('debit_card', $order->payment_method->value);
    }

    public function test_zero_total_order_requires_no_payment_instead_of_recording_a_false_payment(): void
    {
        $admin = User::factory()->administrator()->create();
        $customer = Customer::query()->create(['name' => 'Zero total customer']);
        $payload = $this->payload();
        unset($payload['idempotency_key'], $payload['customer']);
        $payload['items'] = [['product_id' => $this->product->id, 'quantity' => 1]];
        $payload['customer_id'] = $customer->id;
        $payload['payment_status'] = 'paid';
        $payload['payment_method'] = 'cash';
        $payload['discount_type'] = 'percentage';
        $payload['discount_value'] = 100;

        $this->actingAs($admin)->postJson(route('admin.orders.store'), $payload)
            ->assertCreated()
            ->assertJsonPath('data.total', 0)
            ->assertJsonPath('data.payment_status', 'not_required')
            ->assertJsonPath('data.payment_method', null);

        $order = Order::query()->sole();
        $this->assertSame('not_required', $order->payment_status->value);
        $this->assertNull($order->payment_method);

        $this->actingAs($admin)->patch(route('admin.orders.payment-status.update', $order), [
            'payment_status' => 'paid',
            'payment_method' => 'cash',
        ])->assertRedirect();
        $this->assertSame('not_required', $order->refresh()->payment_status->value);
        $this->assertNull($order->payment_method);
    }

    public function test_paid_requires_method_and_unpaid_prohibits_it(): void
    {
        $admin = User::factory()->administrator()->create();
        $customer = Customer::query()->create(['name' => 'Payment customer']);
        $payload = $this->payload();
        unset($payload['idempotency_key'], $payload['customer']);
        $payload['items'] = [['product_id' => $this->product->id, 'quantity' => 1]];
        $payload['customer_id'] = $customer->id;
        $payload['payment_status'] = 'paid';

        $this->actingAs($admin)->post(route('admin.orders.store'), $payload)->assertSessionHasErrors('payment_method');
        $payload['payment_status'] = 'unpaid';
        $payload['payment_method'] = 'cash';
        $this->actingAs($admin)->post(route('admin.orders.store'), $payload)->assertSessionHasErrors('payment_method');
        $this->assertDatabaseCount('orders', 0);
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
