<?php

namespace Tests\Feature\Orders;

use App\Modules\Customers\Models\Customer;
use App\Modules\Identity\Models\User;
use App\Modules\Orders\Enums\OrderCondition;
use App\Modules\Orders\Enums\OrderSettingKey;
use App\Modules\Orders\Enums\PricingMode;
use App\Modules\Orders\Models\Order;
use App\Modules\Orders\Models\OrderSetting;
use App\Modules\Orders\Pricing\OrderPricingEngine;
use App\Modules\Orders\Pricing\PricingLine;
use App\Modules\Products\Models\Category;
use App\Modules\Products\Models\Product;
use App\Modules\Products\Pricing\EmpanadaUnitPrice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

final class CombinedDozenPricingTest extends TestCase
{
    use RefreshDatabase;

    private Category $category;

    /** @var array<string, Product> */
    private array $flavors;

    protected function setUp(): void
    {
        parent::setUp();
        // The required examples are computed with a 25% unit surcharge and a 20% combined dozen discount.
        OrderSetting::query()->where('key', OrderSettingKey::EmpanadaUnitSurchargePercent->value)->update(['integer_value' => 25]);
        OrderSetting::query()->where('key', OrderSettingKey::CombinedDozenDiscountPercent->value)->update(['integer_value' => 20]);
        $this->category = Category::query()->create(['name' => 'Empanadas', 'slug' => 'empanadas', 'sort_order' => 1, 'is_active' => true]);
        $this->flavors = [
            'dulce' => $this->empanada('Dulce', 20000),
            'jyq' => $this->empanada('Jamón y Queso', 22000),
            'salteña' => $this->empanada('Salteña', 18000),
        ];
    }

    public function test_the_fixture_unit_prices_follow_the_25_percent_surcharge(): void
    {
        $this->assertSame([2100, 2300, 1900], array_map(fn (Product $product): int => $product->unit_price, array_values($this->flavors)));
    }

    /** @return array<string, array{array<string, int>, int}> */
    public static function requiredCookedExamples(): array
    {
        return [
            '12 dulce = 20.000' => [['dulce' => 12], 20000],
            '11 dulce + 1 JyQ = 20.300 (combinada 20.320)' => [['dulce' => 11, 'jyq' => 1], 20300],
            '6 dulce + 6 JyQ = 21.100' => [['dulce' => 6, 'jyq' => 6], 21100],
            '6 dulce + 3 JyQ + 3 salteña = 20.200' => [['dulce' => 6, 'jyq' => 3, 'salteña' => 3], 20200],
            '5 dulce + 5 JyQ = 22.000 (no llega a docena)' => [['dulce' => 5, 'jyq' => 5], 22000],
            '13 dulce + 11 JyQ = 41.900' => [['dulce' => 13, 'jyq' => 11], 41900],
            '8 dulce + 8 JyQ = 29.800' => [['dulce' => 8, 'jyq' => 8], 29800],
            '8 dulce + 8 JyQ + 8 salteña = 40.300' => [['dulce' => 8, 'jyq' => 8, 'salteña' => 8], 40300],
            '11 salteña + 1 dulce = 18.400' => [['salteña' => 11, 'dulce' => 1], 18400],
        ];
    }

    /** @param array<string, int> $basket */
    #[DataProvider('requiredCookedExamples')]
    public function test_required_cooked_examples(array $basket, int $total): void
    {
        $this->assertSame($total, $this->engine()->quote($this->items($basket), OrderCondition::Cooked)->total);
    }

    /** @return array<string, array{array<string, int>, int}> */
    public static function requiredRawExamples(): array
    {
        return [
            'Cruda 6 dulce + 6 JyQ = 20.200 (combinada 20.160)' => [['dulce' => 6, 'jyq' => 6], 20200],
            'Cruda 6 + 3 + 3 = 19.200' => [['dulce' => 6, 'jyq' => 3, 'salteña' => 3], 19200],
        ];
    }

    /** @param array<string, int> $basket */
    #[DataProvider('requiredRawExamples')]
    public function test_required_raw_examples(array $basket, int $total): void
    {
        $this->assertSame($total, $this->engine()->quote($this->items($basket), OrderCondition::Raw)->total);
    }

    public function test_the_most_expensive_flavor_enters_the_combined_dozen_first_and_the_rest_goes_to_units(): void
    {
        $quote = $this->engine()->quote($this->items(['dulce' => 8, 'jyq' => 8]), OrderCondition::Cooked);

        $this->assertSame([
            ['unit', $this->flavors['dulce']->id, 4, 4, 8400, 8400, 0],
            ['combined_dozen', null, 1, 12, 21440, 26800, 5360],
        ], array_map(fn (PricingLine $line): array => [$line->pricingMode, $line->productId, $line->quantity, $line->unitsCount, $line->lineTotal, $line->regularPriceTotal, $line->savings], $quote->lines));
        $this->assertSame([
            ['product_id' => $this->flavors['jyq']->id, 'product_name' => 'Jamón y Queso', 'quantity' => 8],
            ['product_id' => $this->flavors['dulce']->id, 'product_name' => 'Dulce', 'quantity' => 4],
        ], $quote->lines[1]->composition);
        $this->assertSame(5360, $quote->savings);
        $this->assertSame(29840, $quote->productsSubtotal);
    }

    public function test_full_dozens_of_one_flavor_are_taken_before_combining_the_remainders(): void
    {
        $quote = $this->engine()->quote($this->items(['dulce' => 13, 'jyq' => 11]), OrderCondition::Cooked);

        $this->assertSame([
            ['dozen', $this->flavors['dulce']->id, 1, 12, 20000],
            ['combined_dozen', null, 1, 12, 21920],
        ], array_map(fn (PricingLine $line): array => [$line->pricingMode, $line->productId, $line->quantity, $line->unitsCount, $line->lineTotal], $quote->lines));
        $this->assertSame([[$this->flavors['jyq']->id, 11], [$this->flavors['dulce']->id, 1]], array_map(fn (array $part): array => [$part['product_id'], $part['quantity']], $quote->lines[1]->composition));
    }

    public function test_raw_combined_dozen_keeps_the_cooked_price_and_the_raw_discount(): void
    {
        $line = $this->engine()->quote($this->items(['dulce' => 6, 'jyq' => 6]), OrderCondition::Raw)->lines[0];

        // Raw dozens at 5% off: dulce 19000 → U 2000, JyQ 20900 → U 2200 → 25200 × 0.8; cooked U 2100 / 2300 → 26400 × 0.8.
        $this->assertSame(['combined_dozen', 20160, 20160, 21120, 21120, 960, 25200, 5040], [
            $line->pricingMode, $line->unitPrice, $line->lineTotal, $line->cookedUnitPrice, $line->cookedLineTotal,
            $line->rawDiscount, $line->regularPriceTotal, $line->savings,
        ]);
    }

    public function test_flavors_with_the_same_unit_price_enter_by_lower_product_id(): void
    {
        // S11: with equal unit prices the lower product ID enters the combined dozen first.
        $twin = $this->empanada('Dulce gemela', 20000);
        $basket = [['product_id' => $twin->id, 'quantity' => 7], ['product_id' => $this->flavors['dulce']->id, 'quantity' => 7]];

        $quote = $this->engine()->quote($basket, OrderCondition::Cooked);

        $this->assertSame(['unit', $twin->id, 2], [$quote->lines[0]->pricingMode, $quote->lines[0]->productId, $quote->lines[0]->quantity]);
        $this->assertSame([[$this->flavors['dulce']->id, 7], [$twin->id, 5]], array_map(fn (array $part): array => [$part['product_id'], $part['quantity']], $quote->lines[1]->composition));
    }

    public function test_the_combined_discount_follows_the_configured_percentage(): void
    {
        OrderSetting::query()->where('key', OrderSettingKey::CombinedDozenDiscountPercent->value)->update(['integer_value' => 10]);

        // 26400 × 0.9 = 23760 → 23800.
        $this->assertSame(23800, $this->engine()->quote($this->items(['dulce' => 6, 'jyq' => 6]), OrderCondition::Cooked)->total);
    }

    public function test_zero_percent_builds_no_combined_dozen_and_the_remainders_sell_at_unit_price(): void
    {
        OrderSetting::query()->where('key', OrderSettingKey::CombinedDozenDiscountPercent->value)->update(['integer_value' => 0]);

        $quote = $this->engine()->quote($this->items(['dulce' => 6, 'jyq' => 6]), OrderCondition::Cooked);

        $this->assertSame([
            ['unit', $this->flavors['dulce']->id, 6, 12600],
            ['unit', $this->flavors['jyq']->id, 6, 13800],
        ], array_map(fn (PricingLine $line): array => [$line->pricingMode, $line->productId, $line->quantity, $line->lineTotal], $quote->lines));
        $this->assertSame(0, $quote->savings);
        $this->assertSame(26400, $quote->total);
    }

    public function test_fifty_percent_halves_the_combined_dozen(): void
    {
        OrderSetting::query()->where('key', OrderSettingKey::CombinedDozenDiscountPercent->value)->update(['integer_value' => 50]);

        $quote = $this->engine()->quote($this->items(['dulce' => 6, 'jyq' => 6]), OrderCondition::Cooked);

        // 26400 × 0.5 = 13200.
        $this->assertSame([['combined_dozen', 13200, 26400, 13200]], array_map(fn (PricingLine $line): array => [$line->pricingMode, $line->lineTotal, $line->regularPriceTotal, $line->savings], $quote->lines));
        $this->assertSame(13200, $quote->total);
    }

    public function test_unit_products_never_join_a_combined_dozen(): void
    {
        $soda = Product::query()->create([
            'category_id' => $this->category->id, 'name' => 'Soda', 'slug' => 'soda', 'pricing_family' => 'unit',
            'unit_price' => 1500, 'half_dozen_price' => 1500, 'dozen_price' => 1500, 'is_available' => true,
        ]);

        $quote = $this->engine()->quote([['product_id' => $this->flavors['dulce']->id, 'quantity' => 6], ['product_id' => $soda->id, 'quantity' => 6]], OrderCondition::Cooked);

        $this->assertSame(['unit', 'unit'], array_map(fn (PricingLine $line): string => $line->pricingMode, $quote->lines));
        $this->assertSame(12600 + 9000, $quote->total);
    }

    public function test_admin_order_persists_the_combined_dozen_snapshot_matching_its_preview(): void
    {
        $admin = User::factory()->administrator()->create();
        $payload = $this->payload(['dulce' => 6, 'jyq' => 3, 'salteña' => 3], 'raw');

        $preview = $this->actingAs($admin)->postJson(route('admin.orders.price-preview'), ['items' => $payload['items'], 'condition' => 'raw', 'delivery_type' => 'pickup'])->assertOk();
        $this->actingAs($admin)->post(route('admin.orders.store'), $payload)->assertRedirect();

        $order = Order::query()->latest('id')->firstOrFail();
        $row = DB::table('orders')->where('id', $order->id)->first();
        // Raw U: dulce 2000, JyQ 2200, salteña 1800 → 24000 × 0.8 = 19200; cooked 25200 × 0.8 = 20160.
        $this->assertSame(19200, $preview->json('data.total'));
        $this->assertSame([2, 19200, 19200, 20160, 960, 4800], [
            (int) $row->pricing_snapshot_version, (int) $row->total, (int) $row->items_subtotal, (int) $row->items_cooked_subtotal,
            (int) $row->raw_discount, (int) $row->combined_dozen_savings,
        ]);

        $item = $order->items()->with('compositions')->sole();
        $this->assertSame([PricingMode::CombinedDozen, null, 1, 12, 19200, 24000, 4800, 2], [
            $item->pricing_mode, $item->product_id, (int) $item->quantity, (int) $item->units_count, (int) $item->line_total,
            (int) $item->regular_price_total, (int) $item->savings, (int) $item->pricing_snapshot_version,
        ]);
        $this->assertSame([
            [$this->flavors['jyq']->id, 'Jamón y Queso', 3],
            [$this->flavors['dulce']->id, 'Dulce', 6],
            [$this->flavors['salteña']->id, 'Salteña', 3],
        ], $item->compositions->sortBy('id')->map(fn ($part): array => [(int) $part->product_id, $part->product_name, (int) $part->quantity])->values()->all());
    }

    private function engine(): OrderPricingEngine
    {
        return app(OrderPricingEngine::class);
    }

    private function empanada(string $name, int $dozen): Product
    {
        return Product::query()->create([
            'category_id' => $this->category->id, 'name' => $name, 'slug' => str($name)->slug()->append('-'.uniqid())->toString(),
            'pricing_family' => 'empanada', 'unit_price' => EmpanadaUnitPrice::fromDozen($dozen, 25),
            'half_dozen_price' => 6 * EmpanadaUnitPrice::fromDozen($dozen, 25), 'dozen_price' => $dozen, 'is_available' => true, 'sort_order' => 1,
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
        $customer = Customer::query()->firstOrCreate(['phone_normalized' => '5491100000000'], ['name' => 'Combined dozen fixture', 'phone' => '+54 9 11 0000-0000']);

        return [
            'items' => $this->items($basket), 'addons' => [],
            'condition' => $condition, 'delivery_type' => 'pickup', 'delivery_period' => 'night',
            'delivery_date' => now()->toDateString(), 'delivery_time' => null, 'delivery_address' => null,
            'notes' => null, 'customer_id' => $customer->id, 'payment_status' => 'unpaid',
        ];
    }
}
