<?php

namespace Tests\Feature\Orders;

use App\Core\Contracts\ProductPriceProvider;
use App\Modules\Orders\Enums\DeliveryType;
use App\Modules\Orders\Enums\DiscountType;
use App\Modules\Orders\Enums\OrderCondition;
use App\Modules\Orders\Enums\OrderSettingKey;
use App\Modules\Orders\Models\OrderAddon;
use App\Modules\Orders\Models\OrderSetting;
use App\Modules\Orders\Pricing\OrderPricingEngine;
use App\Modules\Products\Enums\PriceTier;
use App\Modules\Products\Models\Category;
use App\Modules\Products\Models\Product;
use App\Modules\Products\Models\RawPriceDiscount;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

final class OrderPricingEngineTest extends TestCase
{
    use RefreshDatabase;

    private Category $category;

    protected function setUp(): void
    {
        parent::setUp();
        $this->category = Category::query()->create([
            'name' => 'Empanadas', 'slug' => 'empanadas', 'sort_order' => 1, 'is_active' => true,
        ]);
        RawPriceDiscount::query()->insert([
            ['pricing_mode' => PriceTier::Unit->value, 'amount' => 200],
            ['pricing_mode' => PriceTier::HalfDozen->value, 'amount' => 1000],
            ['pricing_mode' => PriceTier::Dozen->value, 'amount' => 2000],
        ]);
    }

    /** @return array<string, array{int, array<string, int>}> */
    public static function singleFlavorCases(): array
    {
        return [
            'five are units' => [5, ['unit' => 5]],
            'six are units (no half dozen tier)' => [6, ['unit' => 6]],
            'eleven are units (no cap)' => [11, ['unit' => 11]],
            'twelve close a product dozen' => [12, ['dozen' => 1]],
            'eighteen close a dozen plus six units' => [18, ['dozen' => 1, 'unit' => 6]],
        ];
    }

    /** @param array<string, int> $expected */
    #[DataProvider('singleFlavorCases')]
    public function test_single_flavor_grouping_is_deterministic(int $quantity, array $expected): void
    {
        $product = $this->product('A');
        $quote = $this->engine()->quote([['product_id' => $product->id, 'quantity' => $quantity]], OrderCondition::Raw);

        $actual = [];
        foreach ($quote->lines as $line) {
            $actual[$line->pricingMode] = $line->quantity;
        }

        $this->assertSame($expected, $actual);
    }

    public function test_six_mixed_items_remain_units_because_half_dozen_cannot_be_combined(): void
    {
        [$a, $b] = [$this->product('A'), $this->product('B')];
        $quote = $this->engine()->quote($this->items([$a, 3], [$b, 3]), OrderCondition::Raw);

        $this->assertSame(['unit', 'unit'], array_column($quote->toArray()['lines'], 'pricingMode'));
        $this->assertSame(6, array_sum(array_column($quote->toArray()['lines'], 'quantity')));
    }

    public function test_mixed_flavors_never_combine_into_a_dozen(): void
    {
        [$a, $b, $c] = [$this->product('A'), $this->product('B'), $this->product('C')];
        $quote = $this->engine()->quote($this->items([$a, 6], [$b, 3], [$c, 3]), OrderCondition::Raw);

        $this->assertSame(['unit', 'unit', 'unit'], array_column($quote->toArray()['lines'], 'pricingMode'));
        $this->assertSame([[], [], []], array_column($quote->toArray()['lines'], 'composition'));
        // Raw U = (18000 - 2000) / 12 × 1.1 → 1500.
        $this->assertSame(12 * 1500, $quote->total);
    }

    public function test_twenty_four_varied_items_stay_per_flavor_in_stable_product_order(): void
    {
        [$a, $b, $c] = [$this->product('A'), $this->product('B'), $this->product('C')];
        $quote = $this->engine()->quote($this->items([$c, 2], [$b, 11], [$a, 12]), OrderCondition::Raw);

        $this->assertSame([['dozen', $a->id, 1], ['unit', $b->id, 11], ['unit', $c->id, 2]], array_map(
            fn ($line): array => [$line->pricingMode, $line->productId, $line->quantity],
            $quote->lines,
        ));
    }

    public function test_cooked_prices_are_the_stored_dozen_and_unit_prices(): void
    {
        [$a, $b] = [$this->product('A'), $this->product('B')];

        $unit = $this->engine()->quote($this->items([$a, 1]), OrderCondition::Cooked);
        $six = $this->engine()->quote($this->items([$a, 6]), OrderCondition::Cooked);
        $dozen = $this->engine()->quote($this->items([$a, 12]), OrderCondition::Cooked);
        $mixed = $this->engine()->quote($this->items([$a, 6], [$b, 6]), OrderCondition::Cooked);

        $this->assertSame(1800, $unit->total);
        $this->assertSame(10800, $six->total);
        $this->assertSame(18000, $dozen->total);
        $this->assertSame(21600, $mixed->total);
    }

    public function test_cooked_quotes_have_no_raw_discount(): void
    {
        [$a, $b] = [$this->product('A'), $this->product('B')];

        $unit = $this->engine()->quote($this->items([$a, 1]), OrderCondition::Cooked);
        $dozen = $this->engine()->quote($this->items([$a, 12]), OrderCondition::Cooked);
        $mixed = $this->engine()->quote($this->items([$a, 6], [$b, 6]), OrderCondition::Cooked);

        $this->assertSame([1800, 0, 1800], [$unit->lines[0]->cookedLineTotal, $unit->lines[0]->rawDiscount, $unit->total]);
        $this->assertSame([18000, 0, 18000], [$dozen->lines[0]->cookedLineTotal, $dozen->lines[0]->rawDiscount, $dozen->total]);
        $this->assertSame([21600, 0, 21600], [$mixed->productsCookedSubtotal, $mixed->rawDiscount, $mixed->productsSubtotal]);
    }

    public function test_raw_quotes_snapshot_cooked_base_and_positive_discount(): void
    {
        $product = $this->product('A');
        $quote = $this->engine()->quote($this->items([$product, 18]), OrderCondition::Raw);

        // Dozen 18000 - 2000; six units at 1800 cooked and 1500 raw.
        $this->assertSame(18000 + 6 * 1800, $quote->productsCookedSubtotal);
        $this->assertSame(2000 + 6 * 300, $quote->rawDiscount);
        $this->assertSame(16000 + 6 * 1500, $quote->productsSubtotal);
        $this->assertSame([2000, 1800], array_column($quote->toArray()['lines'], 'rawDiscount'));
    }

    public function test_fixed_dozen_quotes_report_no_savings(): void
    {
        [$a, $b] = [$this->product('A'), $this->product('B')];
        $quote = $this->engine()->quote($this->items([$a, 12], [$b, 11]), OrderCondition::Raw);

        $this->assertSame(0, $quote->savings);
        $this->assertSame(array_column($quote->toArray()['lines'], 'lineTotal'), array_column($quote->toArray()['lines'], 'regularPriceTotal'));
    }

    public function test_addons_and_delivery_are_backend_priced_while_pickup_shipping_is_zero(): void
    {
        $product = $this->product('A');
        $lactonesa = OrderAddon::query()->where('name', 'Lactonesa')->firstOrFail();
        OrderSetting::query()->where('key', OrderSettingKey::DeliveryFee->value)->update(['integer_value' => 2500]);

        $delivery = $this->engine()->quote(
            $this->items([$product, 1]), OrderCondition::Raw,
            [['addon_id' => $lactonesa->id, 'quantity' => 2]], DeliveryType::Delivery,
        );
        $pickup = $this->engine()->quote($this->items([$product, 1]), OrderCondition::Raw, [], DeliveryType::Pickup);

        $this->assertSame(1000, $delivery->addonsSubtotal);
        $this->assertSame(2500, $delivery->shippingFee);
        $this->assertSame(1500 + 1000 + 2500, $delivery->total);
        $this->assertSame(0, $pickup->shippingFee);
    }

    public function test_percentage_discount_uses_products_and_addons_but_excludes_shipping(): void
    {
        $product = $this->product('Discounted');
        $addon = OrderAddon::query()->where('name', 'Lactonesa')->firstOrFail();
        OrderSetting::query()->where('key', OrderSettingKey::DeliveryFee->value)->update(['integer_value' => 2500]);

        $quote = $this->engine()->quote(
            $this->items([$product, 1]),
            OrderCondition::Raw,
            [['addon_id' => $addon->id, 'quantity' => 1]],
            DeliveryType::Delivery,
            DiscountType::Percentage,
            10,
        );

        $this->assertSame(2000, $quote->discountableSubtotal);
        $this->assertSame(200, $quote->discountAmount);
        $this->assertSame(2000 - 200 + 2500, $quote->total);
        $this->assertSame('percentage', $quote->toArray()['discount_type']);
    }

    public function test_percentage_discount_is_exact_and_only_the_final_total_rounds(): void
    {
        $product = $this->product('Rounding');
        $product->update(['unit_price' => 1205]);

        $quote = $this->engine()->quote(
            $this->items([$product, 1]),
            OrderCondition::Cooked,
            discountType: DiscountType::Percentage,
            discountValue: 50,
        );

        // 602.5 exact discount is shown as 603; the net 602.5 rounds once to 600.
        $this->assertSame(603, $quote->discountAmount);
        $this->assertSame(600, $quote->total);
    }

    public function test_fixed_discount_is_capped_to_discountable_subtotal_without_consuming_shipping(): void
    {
        $product = $this->product('Capped');
        OrderSetting::query()->where('key', OrderSettingKey::DeliveryFee->value)->update(['integer_value' => 2500]);

        $quote = $this->engine()->quote(
            $this->items([$product, 1]),
            OrderCondition::Raw,
            deliveryType: DeliveryType::Delivery,
            discountType: DiscountType::FixedAmount,
            discountValue: 999999,
        );

        $this->assertSame(1500, $quote->discountAmount);
        $this->assertSame(2500, $quote->total);
    }

    public function test_discount_type_and_value_must_be_valid_and_paired(): void
    {
        $product = $this->product('Invalid discount');

        try {
            $this->engine()->quote($this->items([$product, 1]), OrderCondition::Raw, discountValue: 10);
            $this->fail('An untyped discount should be rejected.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('discount', $exception->errors());
        }

        $this->expectException(ValidationException::class);
        $this->engine()->quote(
            $this->items([$product, 1]),
            OrderCondition::Raw,
            discountType: DiscountType::Percentage,
            discountValue: 101,
        );
    }

    public function test_unavailable_or_deleted_products_and_addons_are_rejected(): void
    {
        $product = $this->product('A');
        $product->delete();

        $this->expectException(ValidationException::class);
        $this->engine()->quote($this->items([$product, 1]), OrderCondition::Raw);
    }

    public function test_quantity_limits_are_enforced_by_the_engine(): void
    {
        $product = $this->product('A');

        $this->expectException(ValidationException::class);
        $this->engine()->quote($this->items([$product, 121]), OrderCondition::Raw);
    }

    public function test_unavailable_addons_are_rejected(): void
    {
        $product = $this->product('A');
        $addon = OrderAddon::query()->where('name', 'Lactonesa')->firstOrFail();
        $addon->update(['is_available' => false]);

        $this->expectException(ValidationException::class);
        $this->engine()->quote(
            $this->items([$product, 1]),
            OrderCondition::Raw,
            [['addon_id' => $addon->id, 'quantity' => 1]],
        );
    }

    public function test_public_product_slugs_resolve_only_sellable_products_without_accepting_ids(): void
    {
        $available = $this->product('Available');
        $unavailable = $this->product('Unavailable');
        $unavailable->update(['is_available' => false]);

        $prices = app(ProductPriceProvider::class)->productsForSaleBySlugs(
            [$available->slug, $unavailable->slug, 'missing-product'],
            false,
        );

        $this->assertSame([$available->slug], array_keys($prices));
        $this->assertSame($available->id, $prices[$available->slug]->id);
        $this->assertSame($available->slug, $prices[$available->slug]->slug);
    }

    public function test_public_addon_slugs_are_stable_and_resolve_only_available_addons(): void
    {
        $lactonesa = OrderAddon::query()->where('slug', 'lactonesa')->firstOrFail();
        $yasgua = OrderAddon::query()->where('slug', 'yasgua')->firstOrFail();
        $yasgua->update(['is_available' => false]);

        $addons = OrderAddon::availableBySlugs(['lactonesa', 'yasgua', 'missing-addon']);

        $this->assertSame(['lactonesa'], $addons->keys()->all());
        $this->assertSame($lactonesa->id, $addons['lactonesa']->id);
    }

    public function test_products_in_inactive_categories_are_rejected_by_admin_and_public_pricing(): void
    {
        $product = $this->product('Hidden');
        $this->category->update(['is_active' => false]);
        $provider = app(ProductPriceProvider::class);

        $this->assertSame([], $provider->productsForSale([$product->id], false));
        $this->assertSame([], $provider->productsForSaleBySlugs([$product->slug], false));

        $this->expectException(ValidationException::class);
        $this->engine()->quote($this->items([$product, 1]), OrderCondition::Raw);
    }

    public function test_products_in_soft_deleted_categories_are_rejected_by_admin_and_public_pricing(): void
    {
        $product = $this->product('Orphaned');
        $this->category->delete();
        $provider = app(ProductPriceProvider::class);

        $this->assertNull($provider->priceFor($product->id, PriceTier::Unit->value, false));
        $this->assertSame([], $provider->productsForSale([$product->id], false));
        $this->assertSame([], $provider->productsForSaleBySlugs([$product->slug], false));
    }

    private function engine(): OrderPricingEngine
    {
        return app(OrderPricingEngine::class);
    }

    private function product(string $name): Product
    {
        return Product::query()->create([
            'category_id' => $this->category->id, 'name' => $name, 'slug' => strtolower($name).'-'.uniqid(),
            'unit_price' => 1800, 'half_dozen_price' => 10000, 'dozen_price' => 18000,
            'is_available' => true, 'sort_order' => 1,
        ]);
    }

    /**
     * @param  array{0: Product, 1: int}  ...$entries
     * @return array<int, array{product_id: int, quantity: int}>
     */
    private function items(array ...$entries): array
    {
        $items = [];
        foreach ($entries as [$product, $quantity]) {
            $items[] = ['product_id' => $product->id, 'quantity' => $quantity];
        }

        return $items;
    }
}
