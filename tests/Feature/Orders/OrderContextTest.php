<?php

namespace Tests\Feature\Orders;

use App\Modules\Customers\Models\Customer;
use App\Modules\Identity\Enums\Role;
use App\Modules\Identity\Models\User;
use App\Modules\Management\Http\Resources\AdminFinancialTransactionResource;
use App\Modules\Management\Models\ManagementOrderPayment;
use App\Modules\Management\Models\Wallet;
use App\Modules\Orders\Actions\BuildAdminOrderEditData;
use App\Modules\Orders\Actions\CreateOrder;
use App\Modules\Orders\Data\OrderCheckoutData;
use App\Modules\Orders\Enums\DeliveryPeriod;
use App\Modules\Orders\Enums\DeliveryType;
use App\Modules\Orders\Enums\OrderCondition;
use App\Modules\Orders\Enums\OrderSource;
use App\Modules\Orders\Enums\PaymentStatus;
use App\Modules\Orders\Http\Resources\AdminOrderResource;
use App\Modules\Orders\Http\Resources\DeletedOrderResource;
use App\Modules\Orders\Models\Order;
use App\Modules\Orders\Models\OrderSetting;
use App\Modules\Orders\Support\OrderSummaryFormatter;
use App\Modules\Products\Models\Category;
use App\Modules\Products\Models\Product;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\OpensPublicOrdering;
use Tests\TestCase;

final class OrderContextTest extends TestCase
{
    use OpensPublicOrdering;
    use RefreshDatabase;

    private Product $product;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->openPublicOrderingAllDay();
        $this->admin = User::factory()->administrator()->create();
        $category = Category::query()->create(['name' => 'Drinks', 'slug' => 'drinks']);
        $this->product = Product::query()->create([
            'category_id' => $category->id, 'name' => 'Water', 'slug' => 'water',
            'pricing_family' => 'unit', 'unit_price' => 2500,
            'half_dozen_price' => 2500, 'dozen_price' => 2500, 'is_available' => true,
        ]);
        OrderSetting::query()->where('key', 'whatsapp_order_phone')->update(['string_value' => '5491112345678']);
    }

    public function test_pos_dine_in_has_no_customer_pii_schedule_or_shipping_and_keeps_unit_pricing(): void
    {
        $order = $this->createDineIn();
        foreach (['customer_id', 'customer_name_snapshot', 'customer_phone_snapshot', 'delivery_address_snapshot', 'delivery_date', 'delivery_period', 'delivery_time'] as $field) {
            $this->assertNull($order->$field, $field);
        }
        $this->assertSame('pos', $order->source->value);
        $this->assertSame('dine_in', $order->delivery_type->value);
        $this->assertSame(0, $order->shipping_fee_snapshot);
        $this->assertSame(7500, $order->total);
        $this->assertSame(3, $order->items->sole()->units_count);
        $this->assertDatabaseCount('customers', 0);
    }

    public function test_dine_in_serializers_summary_and_edit_hydration_are_null_safe_without_extra_customer_queries(): void
    {
        $order = $this->createDineIn();
        DB::enableQueryLog();
        DB::flushQueryLog();
        $resource = (new AdminOrderResource($order))->resolve();
        $deleted = (new DeletedOrderResource($order))->resolve();
        $this->assertCount(0, DB::getQueryLog());
        DB::disableQueryLog();
        $this->assertNull($resource['customer']);
        $this->assertNull($resource['customer_name']);
        $this->assertNull($resource['delivery_date']);
        $this->assertNull($resource['delivery_period']);
        $this->assertNull($deleted['customer_name']);
        $this->assertNull($deleted['delivery_date']);
        foreach (['format', 'formatAdmin'] as $method) {
            $summary = app(OrderSummaryFormatter::class)->$method($order);
            $this->assertStringContainsString('Salón', $summary);
            $this->assertStringNotContainsString('Cliente:', $summary);
            $this->assertStringNotContainsString('Retiro', $summary);
        }
        $draft = app(BuildAdminOrderEditData::class)->execute($order, false)['draft'];
        $this->assertSame('', $draft['customer']['name']);
        $this->assertNull($draft['customer_id']);
        $this->assertSame('', $draft['delivery_date']);
        $this->assertNull($draft['delivery_period']);
        $this->actingAs($this->admin)->putJson(route('admin.orders.update', $order), [
            ...$draft, 'notes' => 'Operational note',
        ])->assertOk();
        $order->refresh();
        $this->assertNull($order->customer_id);
        $this->assertNull($order->delivery_date);
        $this->assertSame('pos', $order->source->value);
        $this->assertSame(7500, $order->total);
    }

    #[DataProvider('invalidDineInFields')]
    public function test_dine_in_rejects_address_and_artificial_partial_schedule(string $field, mixed $value): void
    {
        $this->expectException(ValidationException::class);
        $this->createDineIn([$field => $value]);
    }

    public static function invalidDineInFields(): array
    {
        return [['deliveryAddress', 'Street 123'], ['customerAddress', 'Street 123'], ['deliveryDate', '2030-01-01'], ['deliveryTime', '12:00'], ['deliveryPeriod', DeliveryPeriod::Night]];
    }

    public function test_dine_in_can_keep_real_contact_snapshots_without_creating_a_customer(): void
    {
        $order = $this->createDineIn(['customerName' => 'Guest', 'customerPhone' => '1234']);
        $this->assertNull($order->customer_id);
        $this->assertSame('Guest', $order->customer_name_snapshot);
        $this->assertSame('1234', $order->customer_phone_snapshot);
        $this->assertDatabaseCount('customers', 0);
    }

    #[DataProvider('invalidPublicFields')]
    public function test_public_checkout_keeps_its_contextual_requirements(string $field, mixed $value, string $error): void
    {
        $payload = $this->publicPayload();
        data_set($payload, $field, $value);
        $this->postJson(route('public.orders.store'), $payload)->assertUnprocessable()->assertJsonValidationErrors($error);
        $this->assertDatabaseCount('orders', 0);
    }

    public static function invalidPublicFields(): array
    {
        return [
            ['customer.name', null, 'customer.name'], ['customer.phone', null, 'customer.phone'],
            ['delivery_address', null, 'delivery_address'], ['delivery_type', 'dine_in', 'delivery_type'],
            ['source', 'pos', 'source'], ['delivery_date', '2026-10-26', 'delivery_date'], ['delivery_period', 'night', 'delivery_period'],
        ];
    }

    public function test_public_pickup_needs_no_address_and_source_does_not_mean_delivery(): void
    {
        $payload = $this->publicPayload();
        $payload['delivery_type'] = 'pickup';
        unset($payload['delivery_address']);
        $this->postJson(route('public.orders.store'), $payload)->assertCreated();
        $order = Order::query()->sole();
        $this->assertSame('public_checkout', $order->source->value);
        $this->assertSame('pickup', $order->delivery_type->value);
        $this->assertSame(0, $order->shipping_fee_snapshot);
    }

    public function test_public_price_preview_cannot_quote_dine_in(): void
    {
        $this->postJson(route('public.orders.price-preview'), [
            'items' => [['product_slug' => $this->product->slug, 'quantity' => 1]],
            'condition' => 'cooked', 'delivery_type' => 'dine_in',
        ])->assertUnprocessable()->assertJsonValidationErrors('delivery_type');
    }

    public function test_admin_delivery_requires_address_but_phone_remains_optional_and_pickup_needs_no_address(): void
    {
        $payload = $this->adminPayload();
        $payload['delivery_type'] = 'delivery';
        $this->actingAs($this->admin)->postJson(route('admin.orders.store'), $payload)
            ->assertUnprocessable()->assertJsonValidationErrors('delivery_address');
        $payload['delivery_address'] = 'Street 123';
        $this->postJson(route('admin.orders.store'), $payload)->assertCreated();
        $delivery = Order::query()->sole();
        $this->assertSame('Street 123', $delivery->delivery_address_snapshot);
        $this->assertNull($delivery->customer_phone_snapshot);
        $payload['delivery_type'] = 'pickup';
        unset($payload['delivery_address']);
        $this->postJson(route('admin.orders.store'), $payload)->assertCreated();
        $this->assertSame(0, Order::query()->latest('id')->firstOrFail()->shipping_fee_snapshot);
    }

    public function test_admin_cannot_spoof_pos_source_or_schedule_a_counter_order(): void
    {
        $payload = $this->adminPayload();
        $this->actingAs($this->admin)->postJson(route('admin.orders.store'), $payload + ['source' => 'pos'])
            ->assertUnprocessable()->assertJsonValidationErrors('source');
        // A dine-in order from the admin store is a counter order (CounterOrderTest): immediate, never scheduled.
        $payload['delivery_type'] = 'dine_in';
        $this->postJson(route('admin.orders.store'), $payload)->assertUnprocessable()->assertJsonValidationErrors(['delivery_date', 'delivery_period']);
    }

    #[DataProvider('invalidDatabaseContexts')]
    public function test_postgresql_rejects_impossible_new_contexts(array $attributes): void
    {
        $this->expectException(QueryException::class);
        DB::table('orders')->insert([
            'source' => 'pos', 'delivery_type' => 'dine_in', 'customer_id' => null,
            'customer_name_snapshot' => null, 'delivery_date' => null, 'delivery_period' => null,
            ...$attributes,
        ]);
    }

    public static function invalidDatabaseContexts(): array
    {
        return [
            [['source' => 'public_checkout']], [['source' => 'unknown']], [['delivery_type' => 'unknown']],
            [['delivery_type' => 'pickup']], [['delivery_address_snapshot' => 'Street']],
            [['shipping_fee_snapshot' => 1, 'total' => 1]], [['delivery_period' => 'night']],
        ];
    }

    public function test_context_migration_preserves_historical_rows_and_customer_links_exactly(): void
    {
        $this->actingAs($this->admin)->postJson(route('admin.orders.store'), $this->adminPayload())->assertCreated();
        $this->postJson(route('public.orders.store'), $this->publicPayload())->assertCreated();
        $beforeOrders = DB::table('orders')->orderBy('id')->get()->toJson();
        $beforeItems = DB::table('order_items')->orderBy('id')->get()->toJson();
        $beforeCustomers = DB::table('customers')->orderBy('id')->get()->toJson();
        $migration = require database_path('migrations/2026_10_05_000000_prepare_order_context.php');
        $migration->down();
        $migration->up();
        $this->assertSame($beforeOrders, DB::table('orders')->orderBy('id')->get()->toJson());
        $this->assertSame($beforeItems, DB::table('order_items')->orderBy('id')->get()->toJson());
        $this->assertSame($beforeCustomers, DB::table('customers')->orderBy('id')->get()->toJson());
    }

    public function test_context_migration_refuses_lossy_rollback_after_dine_in_is_used(): void
    {
        $this->createDineIn();
        $migration = require database_path('migrations/2026_10_05_000000_prepare_order_context.php');
        $this->expectException(\RuntimeException::class);
        $migration->down();
    }

    public function test_staff_can_create_unpaid_pos_context_but_ordinary_users_cannot(): void
    {
        foreach (['waiter', 'cashier'] as $profile) {
            $staff = User::factory()->withRole(Role::from($profile))->create();
            $order = app(CreateOrder::class)->execute($this->dineInData(), OrderSource::Pos, $staff->id);
            $this->assertNull($order->customer_id);
            $this->assertSame('unpaid', $order->payment_status->value);
        }
        $ordinary = User::factory()->create();
        $this->expectException(AuthorizationException::class);
        app(CreateOrder::class)->execute($this->dineInData(), OrderSource::Pos, $ordinary->id);
    }

    public function test_pos_cannot_use_public_checkout_idempotency_namespace(): void
    {
        $this->expectException(ValidationException::class);
        app(CreateOrder::class)->execute($this->dineInData(['idempotencyKey' => 'public-only-idempotency-key']), OrderSource::Pos, $this->admin->id);
    }

    public function test_pos_domain_rejects_missing_actor(): void
    {
        $this->expectException(AuthorizationException::class);
        app(CreateOrder::class)->execute($this->dineInData(), OrderSource::Pos);
    }

    public function test_staff_cannot_confirm_payment_through_pos_creation(): void
    {
        $waiter = User::factory()->withRole(Role::Waiter)->create();
        $this->expectException(AuthorizationException::class);
        app(CreateOrder::class)->execute($this->dineInData(['paymentStatus' => PaymentStatus::Paid]), OrderSource::Pos, $waiter->id);
    }

    public function test_domain_rejects_public_and_admin_dine_in_before_customer_creation(): void
    {
        foreach ([OrderSource::Admin, OrderSource::PublicCheckout] as $source) {
            try {
                app(CreateOrder::class)->execute($this->dineInData(), $source, $this->admin->id);
                $this->fail('Non-POS dine-in must be rejected.');
            } catch (ValidationException $exception) {
                $this->assertArrayHasKey('source', $exception->errors());
            }
        }
        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseCount('customers', 0);
    }

    public function test_optional_real_customer_is_retained_and_contact_only_edits_are_audited(): void
    {
        $customer = Customer::query()->create(['name' => 'Real customer', 'address' => 'Profile address', 'phone' => '9999', 'phone_normalized' => '9999']);
        $linked = $this->createDineIn(['customerId' => $customer->id, 'customerName' => $customer->name]);
        $this->assertSame($customer->id, $linked->customer_id);
        $this->assertNull($linked->delivery_address_snapshot);
        $this->assertSame('Profile address', $customer->fresh()->address);
        $this->assertSame($customer->id, (new AdminOrderResource($linked))->resolve()['customer']['id']);
        $order = $this->createDineIn();
        $draft = app(BuildAdminOrderEditData::class)->execute($order, false)['draft'];
        $draft['customer']['name'] = 'Optional contact';
        $draft['customer']['phone'] = '9999';
        $this->actingAs($this->admin)->putJson(route('admin.orders.update', $order), $draft)->assertOk();
        $this->assertNull($order->fresh()->customer_id);
        $this->assertSame('Optional contact', $order->fresh()->customer_name_snapshot);
        $this->assertContains('customer', $order->revisions()->sole()->changed_fields['fields']);
        $this->putJson(route('admin.orders.update', $order), [...$draft, 'source' => 'admin'])
            ->assertUnprocessable()->assertJsonValidationErrors('source');
    }

    public function test_existing_admin_payment_integration_tolerates_customerless_order(): void
    {
        Wallet::query()->create(['name' => 'Existing admin payment wallet', 'is_order_payment_default' => true]);
        $order = $this->createDineIn();
        $this->actingAs($this->admin)->patch(route('admin.orders.payment-status.update', $order), [
            'payment_status' => 'paid', 'payment_method' => 'cash',
        ])->assertRedirect();
        $payment = ManagementOrderPayment::query()->sole();
        $this->assertSame($order->id, $payment->order_id);
        $this->assertSame(7500, $payment->amount);
        $resource = (new AdminFinancialTransactionResource($payment->financialTransaction))->resolve();
        $this->assertNull($resource['management_order_payment']['order']['customer_name']);
        $this->assertNull($order->fresh()->delivery_date);
    }

    private function createDineIn(array $overrides = []): Order
    {
        return app(CreateOrder::class)->execute($this->dineInData($overrides), OrderSource::Pos, $this->admin->id);
    }

    private function dineInData(array $overrides = []): OrderCheckoutData
    {
        return new OrderCheckoutData(...[
            'items' => [['product_id' => $this->product->id, 'quantity' => 3]], 'addons' => [],
            'condition' => OrderCondition::Cooked, 'deliveryType' => DeliveryType::from('dine_in'),
            'deliveryPeriod' => null, 'deliveryDate' => null, 'deliveryTime' => null,
            'deliveryAddress' => null, 'notes' => null, 'customerId' => null,
            'customerName' => null, 'customerPhone' => null, 'customerPhoneNormalized' => null,
            'customerAddress' => null, 'paymentStatus' => PaymentStatus::Unpaid, ...$overrides,
        ]);
    }

    private function publicPayload(): array
    {
        return [
            'items' => [['product_slug' => $this->product->slug, 'quantity' => 1]], 'condition' => 'cooked',
            'delivery_type' => 'delivery',
            'delivery_address' => 'Street 123', 'customer' => ['name' => 'Visitor', 'phone' => '123456789'],
            'idempotency_key' => 'pos1-public-'.bin2hex(random_bytes(12)),
        ];
    }

    private function adminPayload(): array
    {
        return [
            'items' => [['product_id' => $this->product->id, 'quantity' => 1]], 'condition' => 'cooked',
            'delivery_type' => 'pickup', 'delivery_date' => now()->toDateString(), 'delivery_period' => 'night',
            'customer' => ['name' => 'Admin guest'],
        ];
    }
}
