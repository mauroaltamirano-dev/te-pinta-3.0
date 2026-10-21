<?php

namespace Tests\Feature\Products;

use App\Modules\Orders\Enums\OrderCondition;
use App\Modules\Orders\Pricing\OrderPricingEngine;
use App\Modules\Products\Enums\PriceTier;
use App\Modules\Products\Models\Category;
use App\Modules\Products\Models\Product;
use App\Modules\Products\Models\RawPriceDiscount;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

final class ProductPricingFamilyTest extends TestCase
{
    use RefreshDatabase;

    #[DataProvider('unitProductQuotes')]
    public function test_unit_pizza_beverage_and_dessert_are_priced_per_quantity_without_raw_discounts_or_tier_grouping(
        string $name,
        int $quantity,
        OrderCondition $condition,
        int $unitPrice,
    ): void {
        $category = $this->category();
        $product = $this->product($category, $name, strtolower($name), $unitPrice, 111, 222, 'unit');
        $this->rawDiscounts();

        $quote = app(OrderPricingEngine::class)->quote([
            ['product_id' => $product->id, 'quantity' => $quantity],
        ], $condition);

        $this->assertSame($quantity * $unitPrice, $quote->total);
        $this->assertSame(['unit'], array_column($quote->toArray()['lines'], 'pricingMode'));
        $this->assertSame($quantity, $quote->lines[0]->quantity);
        $this->assertSame($quantity * $unitPrice, $quote->lines[0]->lineTotal);
        $this->assertSame(0, $quote->lines[0]->rawDiscount);
    }

    /** @return array<string, array{string, int, OrderCondition, int}> */
    public static function unitProductQuotes(): array
    {
        $cases = [];
        foreach (['Pizza' => 7_000, 'Beverage' => 3_000, 'Dessert' => 4_000] as $name => $unitPrice) {
            foreach ([6, 12] as $quantity) {
                foreach ([OrderCondition::Cooked, OrderCondition::Raw] as $condition) {
                    $cases["{$name} {$quantity} {$condition->value}"] = [$name, $quantity, $condition, $unitPrice];
                }
            }
        }

        return $cases;
    }

    public function test_mixed_quote_combines_empanada_flavors_and_keeps_unit_items_authoritative(): void
    {
        $category = $this->category();
        $criolla = $this->product($category, 'Criolla', 'criolla', 1_200, 6_600, 12_000, 'empanada');
        $jyq = $this->product($category, 'JyQ', 'jyq', 1_700, 9_200, 17_000, 'empanada');
        $beverage = $this->product($category, 'Beverage', 'beverage', 3_000, 111, 222, 'unit');
        $pizza = $this->product($category, 'Pizza', 'pizza', 7_000, 111, 222, 'unit');
        $dessert = $this->product($category, 'Dessert', 'dessert', 4_000, 111, 222, 'unit');
        $this->rawDiscounts();

        $items = [
            ['product_id' => $criolla->id, 'quantity' => 6],
            ['product_id' => $jyq->id, 'quantity' => 6],
            ['product_id' => $beverage->id, 'quantity' => 2],
            ['product_id' => $pizza->id, 'quantity' => 1],
            ['product_id' => $dessert->id, 'quantity' => 2],
        ];
        $cooked = app(OrderPricingEngine::class)->quote($items, OrderCondition::Cooked);
        $raw = app(OrderPricingEngine::class)->quote($items, OrderCondition::Raw);

        // Unit items keep product order; the 12 empanadas form a combined dozen: (6 × 1700 + 6 × 1200) × 0.8 = 13920.
        $this->assertSame(['unit', 'unit', 'unit', 'combined_dozen'], array_column($cooked->toArray()['lines'], 'pricingMode'));
        $this->assertSame(34_900, $cooked->total);
        $this->assertSame(0, $cooked->rawDiscount);
        // Raw U from D - 2000: Criolla 10000 → 900, JyQ 15000 → 1400; (8400 + 5400) × 0.8 = 11040.
        $this->assertSame(32_000, $raw->total);
        $this->assertSame(2_880, $raw->rawDiscount);
        $this->assertSame([0, 0, 0], array_column(array_slice($raw->toArray()['lines'], 0, 3), 'rawDiscount'));
    }

    public function test_unit_items_cannot_complete_an_underfilled_empanada_combined_dozen(): void
    {
        $category = $this->category();
        $criolla = $this->product($category, 'Criolla', 'criolla', 1_200, 6_600, 12_000, 'empanada');
        $jyq = $this->product($category, 'JyQ', 'jyq', 1_700, 9_200, 17_000, 'empanada');
        $beverage = $this->product($category, 'Beverage', 'beverage', 3_000, 111, 222, 'unit');
        $pizza = $this->product($category, 'Pizza', 'pizza', 7_000, 111, 222, 'unit');
        $dessert = $this->product($category, 'Dessert', 'dessert', 4_000, 111, 222, 'unit');
        $this->rawDiscounts();

        $quote = app(OrderPricingEngine::class)->quote([
            ['product_id' => $criolla->id, 'quantity' => 6],
            ['product_id' => $jyq->id, 'quantity' => 5],
            ['product_id' => $beverage->id, 'quantity' => 2],
            ['product_id' => $pizza->id, 'quantity' => 1],
            ['product_id' => $dessert->id, 'quantity' => 2],
        ], OrderCondition::Raw);

        $this->assertSame(['unit', 'unit', 'unit', 'unit', 'unit'], array_column($quote->toArray()['lines'], 'pricingMode'));
        $this->assertSame(33_400, $quote->total);
        $this->assertSame(3_300, $quote->rawDiscount);
        $this->assertSame([0, 0, 0], array_column(array_slice($quote->toArray()['lines'], 2), 'rawDiscount'));
    }

    private function category(): Category
    {
        return Category::query()->create([
            'name' => 'Unrelated category',
            'slug' => 'unrelated-category',
            'sort_order' => 1,
            'is_active' => true,
        ]);
    }

    private function product(Category $category, string $name, string $slug, int $unitPrice, int $halfDozenPrice, int $dozenPrice, string $family): Product
    {
        return Product::query()->create([
            'category_id' => $category->id,
            'pricing_family' => $family,
            'name' => $name,
            'slug' => $slug,
            'unit_price' => $unitPrice,
            'half_dozen_price' => $halfDozenPrice,
            'dozen_price' => $dozenPrice,
            'is_available' => true,
            'sort_order' => 1,
        ]);
    }

    private function rawDiscounts(): void
    {
        RawPriceDiscount::query()->insert([
            ['pricing_mode' => PriceTier::Unit->value, 'amount' => 300],
            ['pricing_mode' => PriceTier::HalfDozen->value, 'amount' => 1_000],
            ['pricing_mode' => PriceTier::Dozen->value, 'amount' => 2_000],
        ]);
    }
}
