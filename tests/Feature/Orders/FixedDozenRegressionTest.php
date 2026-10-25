<?php

namespace Tests\Feature\Orders;

use App\Modules\Customers\Models\Customer;
use App\Modules\Identity\Models\User;
use App\Modules\Orders\Enums\OrderSettingKey;
use App\Modules\Orders\Models\Order;
use App\Modules\Orders\Models\OrderAddon;
use App\Modules\Orders\Models\OrderSetting;
use App\Modules\Products\Models\Category;
use App\Modules\Products\Models\Product;
use App\Modules\Products\Models\RawPriceDiscount;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\LegacyPricingSnapshots;
use Tests\Support\OpensPublicOrdering;
use Tests\TestCase;

/** Independent verifier probes: public HTTP interface only, except fixture setup and v1 history conversion. */
final class FixedDozenRegressionTest extends TestCase
{
    use LegacyPricingSnapshots;
    use OpensPublicOrdering;
    use RefreshDatabase;

    private User $admin;

    private Category $category;

    /** @var array<string, Product> */
    private array $p = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->openPublicOrderingAllDay();
        // A fixed business morning keeps same-day night deliveries valid whatever the wall clock.
        $this->travelTo(CarbonImmutable::parse('2026-10-20 13:00:00 UTC'));
        $this->admin = User::factory()->administrator()->create();
        $this->category = Category::query()->create(['name' => 'Empanadas', 'slug' => 'empanadas', 'sort_order' => 1, 'is_active' => true]);
        foreach (['unit' => 200, 'half_dozen' => 1000, 'dozen' => 2000] as $mode => $amount) {
            RawPriceDiscount::query()->create(['pricing_mode' => $mode, 'amount' => $amount]);
        }
        foreach (['dulce' => 20000, 'jyq' => 22000, 'salteña' => 18000] as $key => $dozen) {
            $this->actingAs($this->admin)->post(route('admin.products.store'), [
                'name' => "Empanada {$key}", 'category_id' => $this->category->id, 'tags' => [], 'pricing_family' => 'empanada',
                'dozen_price' => $dozen, 'is_available' => true, 'sort_order' => 1,
            ])->assertSessionHasNoErrors()->assertRedirect();
            $this->p[$key] = Product::query()->latest('id')->firstOrFail();
        }
        $this->actingAs($this->admin)->post(route('admin.products.store'), [
            'name' => 'Bebida', 'category_id' => $this->category->id, 'tags' => [], 'pricing_family' => 'unit',
            'unit_price' => 1250, 'is_available' => true, 'sort_order' => 2,
        ])->assertSessionHasNoErrors()->assertRedirect();
        $this->p['bebida'] = Product::query()->latest('id')->firstOrFail();
        OrderSetting::query()->updateOrCreate(['key' => OrderSettingKey::WhatsappOrderPhone->value], ['integer_value' => null, 'string_value' => '+54 9 11 1234-5678']);
    }

    public function test_stored_unit_prices_follow_the_formula(): void
    {
        $this->assertSame([1800, 2000, 1700, 1250], array_map(fn (Product $product): int => $product->fresh()->unit_price, array_values($this->p)));
    }

    /** @return array<string, array{array<string, int>, string, int}> */
    public static function examples(): array
    {
        return [
            '12 dulce' => [['dulce' => 12], 'cooked', 20000],
            '11 dulce' => [['dulce' => 11], 'cooked', 19800],
            '10 dulce' => [['dulce' => 10], 'cooked', 18000],
            '13 dulce' => [['dulce' => 13], 'cooked', 21800],
            '24 dulce' => [['dulce' => 24], 'cooked', 40000],
            // Mixed remainders form a combined dozen (20% off the sum of units), replacing fixed-dozen S3.
            '11 dulce + 1 jyq' => [['dulce' => 11, 'jyq' => 1], 'cooked', 17400],
            '6 dulce + 3 jyq + 3 salteña' => [['dulce' => 6, 'jyq' => 3, 'salteña' => 3], 'cooked', 17500],
            '11 salteña' => [['salteña' => 11], 'cooked', 18700],
            'raw dozen dulce' => [['dulce' => 12], 'raw', 19000],
            'raw unit dulce' => [['dulce' => 1], 'raw', 1700],
            'unit product x12 no grouping (15000)' => [['bebida' => 12], 'raw', 15000],
            'unit product 3 x 1250 = 3750 -> 3800' => [['bebida' => 3], 'cooked', 3800],
        ];
    }

    /** @param array<string, int> $basket */
    #[DataProvider('examples')]
    public function test_examples_through_admin_preview_public_preview_and_persisted_orders(array $basket, string $condition, int $total): void
    {
        $admin = $this->actingAs($this->admin)->postJson(route('admin.orders.price-preview'), [
            'items' => $this->items($basket), 'condition' => $condition, 'delivery_type' => 'pickup',
        ])->assertOk();
        $this->assertSame($total, $admin->json('data.total'));

        $public = $this->postJson(route('public.orders.price-preview'), [
            'items' => $this->slugItems($basket), 'condition' => $condition, 'delivery_type' => 'pickup',
        ])->assertOk();
        $this->assertSame($total, $public->json('data.total'));
        $this->assertSame($admin->json('data.lines'), $public->json('data.lines'));

        $this->actingAs($this->admin)->post(route('admin.orders.store'), $this->adminPayload($basket, $condition))->assertSessionHasNoErrors()->assertRedirect();
        $this->assertPersistedEqualsPreview(Order::query()->latest('id')->firstOrFail(), $admin->json('data'));
    }

    public function test_public_checkout_order_matches_its_preview(): void
    {
        $basket = ['dulce' => 13, 'jyq' => 6, 'salteña' => 11];
        $preview = $this->postJson(route('public.orders.price-preview'), [
            'items' => $this->slugItems($basket), 'condition' => 'raw', 'delivery_type' => 'pickup',
        ])->assertOk();
        $this->postJson(route('public.orders.store'), [
            'items' => $this->slugItems($basket), 'addons' => [], 'condition' => 'raw', 'delivery_type' => 'pickup',
            'delivery_address' => null,
            'notes' => null, 'customer' => ['name' => 'Visitor', 'phone' => '+54 9 11 1234-5678', 'address' => 'X'],
            'idempotency_key' => 'verifier-probe-key-000001',
        ])->assertSuccessful();
        $this->assertPersistedEqualsPreview(Order::query()->latest('id')->firstOrFail(), $preview->json('data'));
    }

    /** @return array<string, array{int, int, ?string, ?int, int, int}> */
    public static function roundingCases(): array
    {
        // [unit product price, qty, discount type, discount value, delivery fee, expected total]
        return [
            'exact 50 rounds up' => [1050, 1, null, null, 0, 1100],
            '49 rounds down' => [1049, 1, null, null, 0, 1000],
            'percent net 1049.50 stays 1000 (no double rounding)' => [2099, 1, 'percentage', 50, 0, 1000],
            'percent net 1049.50 + shipping 1 = 1050.50 -> 1100' => [2099, 1, 'percentage', 50, 1, 1100],
            'percent net 1050.50 -> 1100' => [2101, 1, 'percentage', 50, 0, 1100],
            'percent 5% of 1000 = 950 -> 1000' => [1000, 1, 'percentage', 5, 0, 1000],
            'percent 5% of 999 = 949.05 -> 900' => [999, 1, 'percentage', 5, 0, 900],
            'fixed discount above subtotal, shipping 1234 -> 1200' => [1000, 1, 'fixed_amount', 5000, 1234, 1200],
            'fixed 30 on 1080 = 1050 -> 1100' => [1080, 1, 'fixed_amount', 30, 0, 1100],
            '100 percent + shipping 50 -> 100' => [1000, 1, 'percentage', 100, 50, 100],
            '100 percent + shipping 49 -> 0' => [1000, 1, 'percentage', 100, 49, 0],
        ];
    }

    #[DataProvider('roundingCases')]
    public function test_total_rounds_once_half_up_and_persists_like_its_preview(int $price, int $quantity, ?string $type, ?int $value, int $fee, int $expected): void
    {
        DB::table('products')->where('id', $this->p['bebida']->id)->update(['unit_price' => $price, 'half_dozen_price' => $price, 'dozen_price' => $price]);
        OrderSetting::query()->where('key', OrderSettingKey::DeliveryFee->value)->update(['integer_value' => $fee]);
        $delivery = $fee > 0 ? 'delivery' : 'pickup';
        $preview = $this->actingAs($this->admin)->postJson(route('admin.orders.price-preview'), [
            'items' => $this->items(['bebida' => $quantity]), 'condition' => 'cooked', 'delivery_type' => $delivery,
            'discount_type' => $type, 'discount_value' => $value,
        ])->assertOk();
        $this->assertSame($expected, $preview->json('data.total'));

        $payload = [...$this->adminPayload(['bebida' => $quantity], 'cooked'), 'delivery_type' => $delivery, 'discount_type' => $type, 'discount_value' => $value];
        if ($delivery === 'delivery') {
            $payload['delivery_address'] = 'Calle 123';
        }
        $this->actingAs($this->admin)->post(route('admin.orders.store'), $payload)->assertSessionHasNoErrors()->assertRedirect();
        $this->assertPersistedEqualsPreview(Order::query()->latest('id')->firstOrFail(), $preview->json('data'));
    }

    public function test_version_1_history_is_never_recalculated(): void
    {
        $this->actingAs($this->admin)->post(route('admin.orders.store'), $this->adminPayload(['dulce' => 11, 'jyq' => 1], 'raw'))->assertRedirect();
        $order = $this->asLegacyVersion1(Order::query()->latest('id')->firstOrFail());
        $economic = fn (): array => $this->rows('orders', $order->id, ['notes', 'edit_version', 'updated_at']);
        $items = fn (): array => $this->rows('order_items', $order->id);
        [$beforeOrder, $beforeItems] = [$economic(), $items()];

        $this->actingAs($this->admin)->get(route('admin.orders.show', $order))->assertOk();
        $this->actingAs($this->admin)->get(route('admin.orders.edit', $order))->assertOk();
        $this->assertSame([$beforeOrder, $beforeItems], [$economic(), $items()]);

        $this->actingAs($this->admin)->put(route('admin.settings.general.update'), $this->settingsPayload(25))->assertSessionHasNoErrors()->assertRedirect();
        $this->assertSame(2100, $this->p['dulce']->fresh()->unit_price);
        $this->assertSame([$beforeOrder, $beforeItems], [$economic(), $items()]);

        $this->actingAs($this->admin)->put(route('admin.orders.update', $order), [
            ...$this->adminPayload(['dulce' => 11, 'jyq' => 1], 'raw'), 'notes' => 'changed', 'status' => 'waiting', 'expected_version' => $order->fresh()->edit_version,
        ])->assertSessionHasNoErrors()->assertRedirect();
        $this->assertSame('changed', $order->fresh()->notes);
        $this->assertSame([$beforeOrder, $beforeItems], [$economic(), $items()]);
        $this->assertSame(1, (int) DB::table('orders')->where('id', $order->id)->value('pricing_snapshot_version'));
    }

    public function test_settings_percentage_recomputes_every_empanada_only_on_change(): void
    {
        $this->p['salteña']->delete();
        DB::table('products')->where('id', $this->p['jyq']->id)->update(['unit_price' => 9999]);

        // Same percentage: no recomputation (the skewed row stays).
        $this->actingAs($this->admin)->put(route('admin.settings.general.update'), $this->settingsPayload(10))->assertSessionHasNoErrors();
        $this->assertSame(9999, (int) DB::table('products')->where('id', $this->p['jyq']->id)->value('unit_price'));

        $this->actingAs($this->admin)->put(route('admin.settings.general.update'), $this->settingsPayload(0))->assertSessionHasNoErrors();
        $this->assertSame([1700, 1800, 1500, 1250], $this->unitPrices()); // 20000/12=1666.7, 22000/12=1833.3, 18000/12=1500
        $this->actingAs($this->admin)->put(route('admin.settings.general.update'), $this->settingsPayload(100))->assertSessionHasNoErrors();
        $this->assertSame([3300, 3700, 3000, 1250], $this->unitPrices());
        $this->assertSame(2, RawPriceDiscount::query()->whereIn('pricing_mode', ['unit', 'half_dozen'])->count());
    }

    public function test_rejected_requests_leave_the_database_unchanged(): void
    {
        $this->actingAs($this->admin)->post(route('admin.orders.store'), $this->adminPayload(['dulce' => 12], 'cooked'))->assertRedirect();
        $hash = $this->hash();

        // Settings: percentage change with a retired field / invalid values.
        $this->actingAs($this->admin)->putJson(route('admin.settings.general.update'), [...$this->settingsPayload(30), 'combined_dozen_cooked_price' => 1])->assertUnprocessable();
        $this->actingAs($this->admin)->putJson(route('admin.settings.general.update'), [...$this->settingsPayload(30), 'raw_price_discounts' => ['dozen' => 2000, 'unit' => 1]])->assertUnprocessable();
        $this->actingAs($this->admin)->putJson(route('admin.settings.general.update'), [...$this->settingsPayload(30), 'raw_discount_percent' => 51])->assertUnprocessable();
        $this->actingAs($this->admin)->putJson(route('admin.settings.general.update'), $this->settingsPayload(101))->assertUnprocessable();
        $this->actingAs($this->admin)->putJson(route('admin.settings.general.update'), [...$this->settingsPayload(30), 'empanada_unit_surcharge_percent' => ''])->assertUnprocessable();
        // Products: empanada unit price sent.
        $this->actingAs($this->admin)->postJson(route('admin.products.store'), [
            'name' => 'X', 'category_id' => $this->category->id, 'tags' => [], 'pricing_family' => 'empanada', 'unit_price' => 100,
            'dozen_price' => 20000, 'is_available' => true, 'sort_order' => 1,
        ])->assertUnprocessable()->assertJsonValidationErrors('unit_price');
        $this->actingAs($this->admin)->putJson(route('admin.products.update', $this->p['dulce']), [
            'name' => 'Empanada dulce', 'category_id' => $this->category->id, 'tags' => [], 'pricing_family' => 'empanada', 'unit_price' => 100,
            'dozen_price' => 30000, 'is_available' => true, 'sort_order' => 1,
        ])->assertUnprocessable()->assertJsonValidationErrors('unit_price');
        $this->assertSame($hash, $this->hash(), 'settings/products rejection changed the DB');
        // Orders: raw misconfiguration (percentage above 100) through admin creation and public checkout.
        DB::table('order_settings')->where('key', 'raw_discount_percent')->update(['integer_value' => 101]);
        $hash = $this->hash();
        $this->actingAs($this->admin)->postJson(route('admin.orders.store'), $this->adminPayload(['salteña' => 12], 'raw'))->assertUnprocessable();
        $this->postJson(route('public.orders.price-preview'), ['items' => $this->slugItems(['salteña' => 1]), 'condition' => 'raw', 'delivery_type' => 'pickup'])->assertUnprocessable();
        $this->postJson(route('public.orders.store'), [
            'items' => $this->slugItems(['salteña' => 12]), 'addons' => [], 'condition' => 'raw', 'delivery_type' => 'pickup',
            'delivery_address' => null,
            'notes' => null, 'customer' => ['name' => 'New visitor', 'phone' => '+54 9 11 5555-0000', 'address' => 'X'],
            'idempotency_key' => 'verifier-probe-key-000002',
        ])->assertUnprocessable()->assertJsonValidationErrors('items');
        $this->assertSame($hash, $this->hash(), 'raw misconfiguration rejection changed the DB');
        DB::table('order_settings')->where('key', 'raw_discount_percent')->delete();
        $hash = $this->hash();
        $this->actingAs($this->admin)->postJson(route('admin.orders.price-preview'), ['items' => $this->items(['dulce' => 1]), 'condition' => 'raw', 'delivery_type' => 'pickup'])->assertUnprocessable();
        $this->assertSame($hash, $this->hash());
        // More than 50 distinct products.
        $items = [];
        for ($i = 0; $i < 51; $i++) {
            $items[] = ['product_id' => Product::query()->create([
                'category_id' => $this->category->id, 'name' => "P{$i}", 'slug' => "p-{$i}", 'pricing_family' => 'unit',
                'unit_price' => 100, 'half_dozen_price' => 100, 'dozen_price' => 100, 'is_available' => true,
            ])->id, 'quantity' => 1];
        }
        $hash = $this->hash();
        $this->actingAs($this->admin)->postJson(route('admin.orders.price-preview'), ['items' => $items, 'condition' => 'cooked', 'delivery_type' => 'pickup'])->assertUnprocessable();
        $this->actingAs($this->admin)->postJson(route('admin.orders.store'), [...$this->adminPayload([], 'cooked'), 'items' => $items])->assertUnprocessable();
        $this->assertSame($hash, $this->hash());
    }

    public function test_rejected_settings_requests_do_not_change_prices(): void
    {
        $hash = $this->hash();
        $this->actingAs($this->admin)->putJson(route('admin.settings.general.update'), [...$this->settingsPayload(30), 'combined_dozen_cooked_price' => 1])->assertUnprocessable();
        $this->actingAs($this->admin)->putJson(route('admin.settings.general.update'), [...$this->settingsPayload(30), 'raw_price_discounts' => ['dozen' => 18001]])->assertUnprocessable();
        $this->assertSame($hash, $this->hash());
    }

    public function test_settings_page_hides_retired_inputs_and_keeps_rows(): void
    {
        $page = $this->actingAs($this->admin)->get(route('admin.settings.general.index'))->assertOk();
        $props = $page->viewData('page')['props']['orderingSettings'];
        $this->assertArrayNotHasKey('combined_dozen_cooked_price', $props);
        $this->assertArrayNotHasKey('combined_dozen_raw_price', $props);
        $this->assertArrayNotHasKey('raw_price_discounts', $props);
        $this->assertSame(5, $props['raw_discount_percent']);
        $this->assertSame(10, $props['empanada_unit_surcharge_percent']);
        $this->assertDatabaseHas('order_settings', ['key' => 'combined_dozen_cooked_price']);
        $this->assertDatabaseHas('order_settings', ['key' => 'combined_dozen_raw_price']);
    }

    /** @param array<string, mixed> $preview */
    private function assertPersistedEqualsPreview(Order $order, array $preview): void
    {
        $row = DB::table('orders')->where('id', $order->id)->first();
        $this->assertSame(2, (int) $row->pricing_snapshot_version);
        $this->assertSame($preview['total'], (int) $row->total);
        $this->assertSame([$preview['products_subtotal'], $preview['products_cooked_subtotal'], $preview['raw_discount'], $preview['discount_amount'], $preview['shipping_fee']],
            [(int) $row->items_subtotal, (int) $row->items_cooked_subtotal, (int) $row->raw_discount, (int) $row->discount_amount, (int) $row->shipping_fee_snapshot]);
        $items = DB::table('order_items')->where('order_id', $order->id)->orderBy('id')->get();
        $this->assertSame(
            array_map(fn (array $line): array => [$line['pricingMode'], $line['productId'], $line['quantity'], $line['unitsCount'], $line['unitPrice'], $line['lineTotal'], $line['cookedUnitPrice'], $line['rawDiscount']], $preview['lines']),
            $items->map(fn ($item): array => [$item->pricing_mode, $item->product_id === null ? null : (int) $item->product_id, (int) $item->quantity, (int) $item->units_count, (int) $item->unit_price, (int) $item->line_total, (int) $item->cooked_unit_price, (int) $item->raw_discount])->all(),
        );
        $this->assertSame([2], $items->pluck('pricing_snapshot_version')->map(fn ($v): int => (int) $v)->unique()->values()->all());
        $this->assertSame($preview['rounding_adjustment'], Order::query()->findOrFail($order->id)->roundingAdjustment());
    }

    /** @return list<int> */
    private function unitPrices(): array
    {
        return array_map(fn (Product $product): int => (int) DB::table('products')->where('id', $product->id)->value('unit_price'), array_values($this->p));
    }

    /** @param list<string> $except */
    private function rows(string $table, int $orderId, array $except = []): array
    {
        $column = $table === 'orders' ? 'id' : 'order_id';

        return DB::table($table)->where($column, $orderId)->orderBy('id')->get()
            ->map(fn ($row): array => array_diff_key((array) $row, array_flip($except)))->all();
    }

    private function hash(): string
    {
        $data = [];
        foreach (['orders', 'order_items', 'order_item_compositions', 'order_toppings', 'order_revisions', 'customers', 'products', 'order_settings', 'raw_price_discounts', 'order_addons'] as $table) {
            $data[$table] = DB::table($table)->orderBy('id')->get()->all();
        }

        return md5(json_encode($data));
    }

    /** @param array<string, int> $basket */
    private function items(array $basket): array
    {
        return array_map(fn (string $key, int $quantity): array => ['product_id' => $this->p[$key]->id, 'quantity' => $quantity], array_keys($basket), $basket);
    }

    /** @param array<string, int> $basket */
    private function slugItems(array $basket): array
    {
        return array_map(fn (string $key, int $quantity): array => ['product_slug' => $this->p[$key]->slug, 'quantity' => $quantity], array_keys($basket), $basket);
    }

    /** @param array<string, int> $basket */
    private function adminPayload(array $basket, string $condition): array
    {
        $customer = Customer::query()->firstOrCreate(['phone_normalized' => '5491100000000'], ['name' => 'Probe', 'phone' => '+54 9 11 0000-0000']);

        return [
            'items' => $this->items($basket), 'addons' => [], 'condition' => $condition, 'delivery_type' => 'pickup', 'delivery_period' => 'night',
            'delivery_date' => now()->toDateString(), 'delivery_time' => null, 'delivery_address' => null,
            'notes' => 'Original note', 'customer_id' => $customer->id, 'payment_status' => 'unpaid',
        ];
    }

    private function settingsPayload(int $percent): array
    {
        return [
            'empanada_unit_surcharge_percent' => $percent, 'combined_dozen_discount_percent' => 20, 'delivery_fee' => (int) OrderSetting::integer(OrderSettingKey::DeliveryFee),
            'whatsapp_order_phone' => '+54 9 11 1234-5678', 'raw_discount_percent' => 5,
            'addons' => OrderAddon::query()->orderBy('sort_order')->get()->map(fn (OrderAddon $addon): array => [
                'id' => $addon->id, 'name' => $addon->name, 'price' => $addon->price, 'is_available' => $addon->is_available, 'sort_order' => $addon->sort_order,
            ])->all(),
        ];
    }
}
