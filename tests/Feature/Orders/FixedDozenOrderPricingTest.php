<?php

namespace Tests\Feature\Orders;

use App\Modules\Customers\Models\Customer;
use App\Modules\Identity\Models\User;
use App\Modules\Orders\Enums\DeliveryType;
use App\Modules\Orders\Enums\DiscountType;
use App\Modules\Orders\Enums\OrderCondition;
use App\Modules\Orders\Enums\OrderSettingKey;
use App\Modules\Orders\Enums\PricingMode;
use App\Modules\Orders\Http\Resources\AdminOrderResource;
use App\Modules\Orders\Models\Order;
use App\Modules\Orders\Models\OrderAddon;
use App\Modules\Orders\Models\OrderSetting;
use App\Modules\Orders\Pricing\OrderPricingEngine;
use App\Modules\Orders\Pricing\PricingLine;
use App\Modules\Orders\Support\OrderSummaryFormatter;
use App\Modules\Products\Enums\PriceTier;
use App\Modules\Products\Models\Category;
use App\Modules\Products\Models\Product;
use App\Modules\Products\Models\RawPriceDiscount;
use App\Modules\Products\Pricing\EmpanadaUnitPrice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\LegacyPricingSnapshots;
use Tests\TestCase;

final class FixedDozenOrderPricingTest extends TestCase
{
    use LegacyPricingSnapshots;
    use RefreshDatabase;

    private Category $category;

    /** @var array<string, Product> */
    private array $flavors;

    protected function setUp(): void
    {
        parent::setUp();
        $this->category = Category::query()->create(['name' => 'Empanadas', 'slug' => 'empanadas', 'sort_order' => 1, 'is_active' => true]);
        RawPriceDiscount::query()->insert([
            ['pricing_mode' => PriceTier::Unit->value, 'amount' => 200],
            ['pricing_mode' => PriceTier::HalfDozen->value, 'amount' => 1000],
            ['pricing_mode' => PriceTier::Dozen->value, 'amount' => 2000],
        ]);
        $this->flavors = [
            'dulce' => $this->empanada('Dulce', 20000),
            'jyq' => $this->empanada('Jamón y Queso', 22000),
            'salteña' => $this->empanada('Salteña', 18000),
        ];
    }

    public function test_the_required_flavors_store_their_derived_unit_prices(): void
    {
        $this->assertSame([1800, 2000, 1700], array_map(fn (Product $product): int => $product->unit_price, array_values($this->flavors)));
    }

    /** @return array<string, array{array<string, int>, int}> */
    public static function requiredExamples(): array
    {
        return [
            '12 dulce = 20.000' => [['dulce' => 12], 20000],
            '11 dulce = 19.800' => [['dulce' => 11], 19800],
            '10 dulce = 18.000' => [['dulce' => 10], 18000],
            '13 dulce = 21.800' => [['dulce' => 13], 21800],
            '24 dulce = 40.000' => [['dulce' => 24], 40000],
            '11 dulce + 1 JyQ = 21.800' => [['dulce' => 11, 'jyq' => 1], 21800],
            '6 dulce + 3 JyQ + 3 salteña = 21.900' => [['dulce' => 6, 'jyq' => 3, 'salteña' => 3], 21900],
            '11 salteña = 18.700 (above its 18.000 dozen, no cap)' => [['salteña' => 11], 18700],
        ];
    }

    /** @param array<string, int> $basket */
    #[DataProvider('requiredExamples')]
    public function test_required_cooked_examples(array $basket, int $total): void
    {
        $quote = $this->engine()->quote($this->items($basket), OrderCondition::Cooked);

        $this->assertSame($total, $quote->total);
        $this->assertSame($total, $quote->productsSubtotal);
        $this->assertSame(0, $quote->savings);
        $this->assertNotContains('combined_dozen', array_map(fn (PricingLine $line): string => $line->pricingMode, $quote->lines));
    }

    public function test_each_flavor_takes_its_own_dozens_and_prices_the_rest_as_units(): void
    {
        $quote = $this->engine()->quote($this->items(['dulce' => 13, 'jyq' => 6, 'salteña' => 6]), OrderCondition::Cooked);

        $this->assertSame([
            ['dozen', $this->flavors['dulce']->id, 1, 12, 20000, 20000],
            ['unit', $this->flavors['dulce']->id, 1, 1, 1800, 1800],
            ['unit', $this->flavors['jyq']->id, 6, 6, 2000, 12000],
            ['unit', $this->flavors['salteña']->id, 6, 6, 1700, 10200],
        ], array_map(fn (PricingLine $line): array => [$line->pricingMode, $line->productId, $line->quantity, $line->unitsCount, $line->unitPrice, $line->lineTotal], $quote->lines));
        $this->assertSame(44000, $quote->total);
    }

    public function test_cooking_at_home_takes_the_dozen_discount_and_then_the_same_formula(): void
    {
        $dozen = $this->engine()->quote($this->items(['dulce' => 12]), OrderCondition::Raw);
        $unit = $this->engine()->quote($this->items(['dulce' => 1]), OrderCondition::Raw);

        $this->assertSame(18000, $dozen->total);
        $this->assertSame([18000, 20000, 2000], [$dozen->lines[0]->unitPrice, $dozen->lines[0]->cookedUnitPrice, $dozen->rawDiscount]);
        $this->assertSame(1700, $unit->total);
        $this->assertSame([1700, 1800, 100], [$unit->lines[0]->unitPrice, $unit->lines[0]->cookedUnitPrice, $unit->rawDiscount]);
        $this->assertSame(1800, $unit->productsCookedSubtotal);
    }

    public function test_unit_and_half_dozen_raw_discounts_are_no_longer_used(): void
    {
        RawPriceDiscount::query()->whereIn('pricing_mode', [PriceTier::Unit->value, PriceTier::HalfDozen->value])->update(['amount' => 999999]);

        $quote = $this->engine()->quote($this->items(['dulce' => 13]), OrderCondition::Raw);

        $this->assertSame(18000 + 1700, $quote->total);
        $this->assertSame(2, RawPriceDiscount::query()->whereIn('pricing_mode', [PriceTier::Unit->value, PriceTier::HalfDozen->value])->count());
    }

    public function test_the_order_charges_the_stored_unit_price_not_a_per_order_recalculation(): void
    {
        // Settings recompute stored prices; the engine never re-derives the cooked unit price.
        DB::table('products')->where('id', $this->flavors['dulce']->id)->update(['unit_price' => 1900]);

        $this->assertSame(1900, $this->engine()->quote($this->items(['dulce' => 1]), OrderCondition::Cooked)->total);
    }

    public function test_unit_products_stay_quantity_times_unit_price_without_grouping(): void
    {
        $pizza = Product::query()->create([
            'category_id' => $this->category->id, 'name' => 'Pizza', 'slug' => 'pizza', 'pricing_family' => 'unit',
            'unit_price' => 7050, 'half_dozen_price' => 7050, 'dozen_price' => 7050, 'is_available' => true,
        ]);

        $quote = $this->engine()->quote([['product_id' => $pizza->id, 'quantity' => 12]], OrderCondition::Raw);

        $this->assertSame([['unit', 12, 7050, 84600, 0]], array_map(fn (PricingLine $line): array => [$line->pricingMode, $line->quantity, $line->unitPrice, $line->lineTotal, $line->rawDiscount], $quote->lines));
        $this->assertSame(84600, $quote->total);
    }

    public function test_the_final_total_rounds_once_after_addons_shipping_and_discount(): void
    {
        OrderSetting::query()->where('key', OrderSettingKey::DeliveryFee->value)->update(['integer_value' => 1234]);
        $addon = OrderAddon::query()->create(['name' => 'Salsa', 'slug' => 'salsa', 'price' => 333, 'is_available' => true, 'sort_order' => 1]);

        // 19800 + 333 = 20133; 10% = 2013.3; 18119.7 + 1234 = 19353.7 → 19400.
        $quote = $this->engine()->quote($this->items(['dulce' => 11]), OrderCondition::Cooked, [['addon_id' => $addon->id, 'quantity' => 1]], DeliveryType::Delivery, DiscountType::Percentage, 10);

        $this->assertSame(19400, $quote->total);
        $this->assertSame(2013, $quote->discountAmount);
        $this->assertSame(19400 - (19800 + 333 - 2013 + 1234), $quote->roundingAdjustment);
        $this->assertSame(19400, $quote->toArray()['total']);
        $this->assertSame($quote->roundingAdjustment, $quote->toArray()['rounding_adjustment']);
    }

    public function test_invalid_raw_configuration_is_a_validation_error_not_a_server_error(): void
    {
        $admin = User::factory()->administrator()->create();
        $preview = fn () => $this->actingAs($admin)->postJson(route('admin.orders.price-preview'), [
            'items' => $this->items(['dulce' => 12]), 'condition' => 'raw', 'delivery_type' => 'pickup',
        ]);

        RawPriceDiscount::query()->where('pricing_mode', PriceTier::Dozen->value)->update(['amount' => 20001]);
        $preview()->assertUnprocessable()->assertJsonValidationErrors('items');

        RawPriceDiscount::query()->where('pricing_mode', PriceTier::Dozen->value)->delete();
        $preview()->assertUnprocessable()->assertJsonValidationErrors('items');

        // Cooked orders do not depend on raw configuration at all.
        $this->actingAs($admin)->postJson(route('admin.orders.price-preview'), [
            'items' => $this->items(['dulce' => 12]), 'condition' => 'cooked', 'delivery_type' => 'pickup',
        ])->assertOk()->assertJsonPath('data.total', 20000);
    }

    public function test_more_than_fifty_distinct_products_is_a_validation_error(): void
    {
        $items = [];
        for ($index = 0; $index < 51; $index++) {
            $items[] = ['product_id' => $this->empanada("P{$index}", 12000)->id, 'quantity' => 1];
        }

        try {
            $this->engine()->quote($items, OrderCondition::Cooked);
            $this->fail('A quote with 51 distinct products should be rejected.');
        } catch (ValidationException $exception) {
            $this->assertSame(['Un pedido admite como máximo 50 productos distintos.'], $exception->errors()['items']);
        }
    }

    public function test_admin_order_persists_a_version_2_snapshot_matching_its_preview(): void
    {
        $admin = User::factory()->administrator()->create();
        $payload = $this->payload(['dulce' => 6, 'jyq' => 3, 'salteña' => 3], 'raw');

        $preview = $this->actingAs($admin)->postJson(route('admin.orders.price-preview'), ['items' => $payload['items'], 'condition' => 'raw', 'delivery_type' => 'pickup'])->assertOk();
        $this->actingAs($admin)->post(route('admin.orders.store'), $payload)->assertRedirect();

        $order = Order::query()->latest('id')->firstOrFail();
        $row = DB::table('orders')->where('id', $order->id)->first();
        // Raw U: dulce 1700, JyQ (20000) 1800, salteña (16000) 1500 → 10200 + 5400 + 4500 = 20100.
        $this->assertSame(20100, $preview->json('data.total'));
        $this->assertSame([2, 20100, 20100, 21900, 1800], [(int) $row->pricing_snapshot_version, (int) $row->total, (int) $row->items_subtotal, (int) $row->items_cooked_subtotal, (int) $row->raw_discount]);
        $this->assertSame([20100 * 12, 21900 * 12, 1800 * 12, 0], [(int) $row->product_exact_numerator_12, (int) $row->cooked_product_exact_numerator_12, (int) $row->raw_discount_exact_numerator_12, (int) $row->rounding_adjustment_numerator_1200]);

        $items = $order->items()->orderBy('id')->get();
        $this->assertSame([PricingMode::Unit, PricingMode::Unit, PricingMode::Unit], $items->map->pricing_mode->all());
        $this->assertSame([[1700, 1800, 600], [1800, 2000, 600], [1500, 1700, 600]], $items->map(fn ($item) => [(int) $item->unit_price, (int) $item->cooked_unit_price, (int) $item->raw_discount])->all());
        $this->assertSame([2], DB::table('order_items')->where('order_id', $order->id)->distinct()->pluck('pricing_snapshot_version')->map(fn ($version) => (int) $version)->all());
    }

    public function test_summaries_and_admin_resource_show_the_rounding(): void
    {
        $this->actingAs(User::factory()->administrator()->create())->post(route('admin.orders.store'), [
            ...$this->payload(['dulce' => 12], 'cooked'), 'discount_type' => 'fixed_amount', 'discount_value' => 30,
        ])->assertRedirect();
        $order = Order::query()->latest('id')->with(['items.compositions', 'toppings'])->firstOrFail();
        $formatter = app(OrderSummaryFormatter::class);

        // 20000 − 30 = 19970 → 20000.
        $this->assertSame(20000, $order->total);
        $this->assertStringContainsString('Redondeo: +$30', $formatter->formatAdmin($order));
        $this->assertStringContainsString('Redondeo: +$30', $formatter->format($order));
        $this->assertSame(30, (new AdminOrderResource($order))->resolve()['rounding_adjustment']);
    }

    public function test_historical_orders_keep_their_snapshot_after_price_changes_and_metadata_edits(): void
    {
        $admin = User::factory()->administrator()->create();
        $this->actingAs($admin)->post(route('admin.orders.store'), $this->payload(['dulce' => 11], 'cooked'))->assertRedirect();
        $order = $this->asLegacyVersion1(Order::query()->latest('id')->firstOrFail());
        $before = (array) DB::table('orders')->where('id', $order->id)->first();
        $beforeItems = DB::table('order_items')->where('order_id', $order->id)->orderBy('id')->get()->map(fn ($item) => (array) $item)->all();
        $this->flavors['dulce']->update(['dozen_price' => 30000, 'unit_price' => 2800]);
        OrderSetting::query()->where('key', OrderSettingKey::EmpanadaUnitSurchargePercent->value)->update(['integer_value' => 50]);

        $payload = [...$this->payload(['dulce' => 11], 'cooked'), 'notes' => 'Only a note changed', 'status' => 'waiting', 'expected_version' => $order->edit_version];
        $this->actingAs($admin)->put(route('admin.orders.update', $order), $payload)->assertRedirect();

        $after = (array) DB::table('orders')->where('id', $order->id)->first();
        $this->assertSame('Only a note changed', $after['notes']);
        foreach (['pricing_snapshot_version', 'items_subtotal', 'items_cooked_subtotal', 'raw_discount', 'discount_amount', 'total', 'product_exact_numerator_12'] as $field) {
            $this->assertEquals($before[$field], $after[$field], $field);
        }
        $this->assertEquals($beforeItems, DB::table('order_items')->where('order_id', $order->id)->orderBy('id')->get()->map(fn ($item) => (array) $item)->all());
        $this->assertSame((new AdminOrderResource($order->fresh()))->resolve()['rounding_adjustment'], 0);
    }

    private function engine(): OrderPricingEngine
    {
        return app(OrderPricingEngine::class);
    }

    private function empanada(string $name, int $dozen): Product
    {
        return Product::query()->create([
            'category_id' => $this->category->id, 'name' => $name, 'slug' => str($name)->slug()->append('-'.uniqid())->toString(),
            'pricing_family' => 'empanada', 'unit_price' => EmpanadaUnitPrice::fromDozen($dozen, 10),
            'half_dozen_price' => intdiv($dozen, 2) + 1000, 'dozen_price' => $dozen, 'is_available' => true, 'sort_order' => 1,
        ]);
    }

    /**
     * @param  array<string, int>  $basket
     * @return list<array{product_id: int, quantity: int}>
     */
    private function items(array $basket): array
    {
        $items = [];
        foreach ($basket as $flavor => $quantity) {
            $items[] = ['product_id' => $this->flavors[$flavor]->id, 'quantity' => $quantity];
        }

        return $items;
    }

    /**
     * @param  array<string, int>  $basket
     * @return array<string, mixed>
     */
    private function payload(array $basket, string $condition): array
    {
        $customer = Customer::query()->firstOrCreate(['phone_normalized' => '5491100000000'], ['name' => 'Fixed dozen fixture', 'phone' => '+54 9 11 0000-0000']);

        return [
            'items' => $this->items($basket), 'addons' => [],
            'condition' => $condition, 'delivery_type' => 'pickup', 'delivery_period' => 'night',
            'delivery_date' => now()->toDateString(), 'delivery_time' => null, 'delivery_address' => null,
            'notes' => 'Original note', 'customer_id' => $customer->id, 'payment_status' => 'unpaid',
        ];
    }
}
