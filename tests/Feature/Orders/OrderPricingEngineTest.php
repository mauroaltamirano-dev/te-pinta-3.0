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
use App\Modules\Products\Models\CookingSurcharge;
use App\Modules\Products\Models\Product;
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
        CookingSurcharge::query()->insert([
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
            'six close a half dozen' => [6, ['half_dozen' => 1]],
            'seven close a half plus unit' => [7, ['half_dozen' => 1, 'unit' => 1]],
            'twelve close a product dozen' => [12, ['dozen' => 1]],
            'eighteen close dozen plus half' => [18, ['dozen' => 1, 'half_dozen' => 1]],
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

    public function test_six_plus_three_plus_three_closes_combined_dozen_before_half_dozen(): void
    {
        [$a, $b, $c] = [$this->product('A'), $this->product('B'), $this->product('C')];
        $quote = $this->engine()->quote($this->items([$a, 6], [$b, 3], [$c, 3]), OrderCondition::Raw);

        $this->assertCount(1, $quote->lines);
        $this->assertSame('combined_dozen', $quote->lines[0]->pricingMode);
        $this->assertSame([6, 3, 3], array_column($quote->lines[0]->composition, 'quantity'));
        $this->assertSame(16000, $quote->lines[0]->lineTotal);
    }

    public function test_six_plus_six_closes_combined_dozen(): void
    {
        [$a, $b] = [$this->product('A'), $this->product('B')];
        $quote = $this->engine()->quote($this->items([$a, 6], [$b, 6]), OrderCondition::Raw);

        $this->assertSame('combined_dozen', $quote->lines[0]->pricingMode);
    }

    public function test_twenty_four_varied_items_close_two_combined_dozens_in_stable_product_order(): void
    {
        [$a, $b, $c] = [$this->product('A'), $this->product('B'), $this->product('C')];
        $quote = $this->engine()->quote($this->items([$c, 2], [$b, 11], [$a, 11]), OrderCondition::Raw);

        $this->assertCount(2, $quote->lines);
        $this->assertSame(['combined_dozen', 'combined_dozen'], array_column($quote->toArray()['lines'], 'pricingMode'));
        $this->assertSame([$a->id, $b->id], array_column($quote->lines[0]->composition, 'product_id'));
        $this->assertSame([11, 1], array_column($quote->lines[0]->composition, 'quantity'));
        $this->assertSame([$b->id, $c->id], array_column($quote->lines[1]->composition, 'product_id'));
    }

    public function test_cooked_prices_use_product_surcharges_and_fixed_combined_price(): void
    {
        [$a, $b] = [$this->product('A'), $this->product('B')];

        $unit = $this->engine()->quote($this->items([$a, 1]), OrderCondition::Cooked);
        $half = $this->engine()->quote($this->items([$a, 6]), OrderCondition::Cooked);
        $dozen = $this->engine()->quote($this->items([$a, 12]), OrderCondition::Cooked);
        $combined = $this->engine()->quote($this->items([$a, 6], [$b, 6]), OrderCondition::Cooked);

        $this->assertSame(1800, $unit->total);
        $this->assertSame(10000, $half->total);
        $this->assertSame(18000, $dozen->total);
        $this->assertSame(17500, $combined->total);
    }

    public function test_cooking_breakdown_uses_each_commercial_tier_without_changing_totals(): void
    {
        [$a, $b] = [$this->product('A'), $this->product('B')];

        $unit = $this->engine()->quote($this->items([$a, 1]), OrderCondition::Cooked);
        $half = $this->engine()->quote($this->items([$a, 6]), OrderCondition::Cooked);
        $dozen = $this->engine()->quote($this->items([$a, 12]), OrderCondition::Cooked);
        $combined = $this->engine()->quote($this->items([$a, 6], [$b, 6]), OrderCondition::Cooked);

        $this->assertSame([1600, 200, 1800], [
            $unit->lines[0]->baseLineTotal,
            $unit->lines[0]->cookingSurcharge,
            $unit->total,
        ]);
        $this->assertSame([9000, 1000, 10000], [
            $half->lines[0]->baseLineTotal,
            $half->lines[0]->cookingSurcharge,
            $half->total,
        ]);
        $this->assertSame([16000, 2000, 18000], [
            $dozen->lines[0]->baseLineTotal,
            $dozen->lines[0]->cookingSurcharge,
            $dozen->total,
        ]);
        $this->assertSame([16000, 1500, 17500], [
            $combined->lines[0]->baseLineTotal,
            $combined->lines[0]->cookingSurcharge,
            $combined->total,
        ]);
        $this->assertSame(16000, $combined->productsBaseSubtotal);
        $this->assertSame(1500, $combined->cookingSurcharge);
        $this->assertSame(17500, $combined->productsSubtotal);
    }

    public function test_raw_quotes_snapshot_the_applied_price_as_base_with_zero_cooking(): void
    {
        $product = $this->product('A');
        $quote = $this->engine()->quote($this->items([$product, 18]), OrderCondition::Raw);

        $this->assertSame(25000, $quote->productsBaseSubtotal);
        $this->assertSame(0, $quote->cookingSurcharge);
        $this->assertSame(25000, $quote->productsSubtotal);
        $this->assertSame([0, 0], array_column($quote->toArray()['lines'], 'cookingSurcharge'));
    }

    public function test_positive_combined_savings_are_reported_but_never_negative(): void
    {
        [$a, $b] = [$this->product('A'), $this->product('B')];
        $quote = $this->engine()->quote($this->items([$a, 6], [$b, 6]), OrderCondition::Raw);
        $this->assertSame(2000, $quote->savings);
        $this->assertSame(18000, $quote->lines[0]->regularPriceTotal);

        OrderSetting::query()->where('key', OrderSettingKey::CombinedDozenRawPrice->value)->update(['integer_value' => 20000]);
        $quote = $this->engine()->quote($this->items([$a, 6], [$b, 6]), OrderCondition::Raw);
        $this->assertSame(0, $quote->savings);
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
        $this->assertSame(5100, $delivery->total);
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

        $this->assertSame(2100, $quote->discountableSubtotal);
        $this->assertSame(210, $quote->discountAmount);
        $this->assertSame(4390, $quote->total);
        $this->assertSame('percentage', $quote->toArray()['discount_type']);
    }

    public function test_percentage_discount_rounds_half_up_to_integer_ars(): void
    {
        $product = $this->product('Rounding');
        $product->update(['unit_price' => 1005]);

        $quote = $this->engine()->quote(
            $this->items([$product, 1]),
            OrderCondition::Raw,
            discountType: DiscountType::Percentage,
            discountValue: 50,
        );

        $this->assertSame(503, $quote->discountAmount);
        $this->assertSame(502, $quote->total);
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

        $this->assertSame(1600, $quote->discountAmount);
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
            'unit_price' => 1600, 'half_dozen_price' => 9000, 'dozen_price' => 16000,
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
