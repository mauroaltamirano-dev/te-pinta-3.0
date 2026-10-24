<?php

namespace Tests\Feature\Products;

use App\Core\Contracts\ProductPriceProvider;
use App\Modules\Identity\Models\User;
use App\Modules\Orders\Enums\OrderSettingKey;
use App\Modules\Orders\Models\OrderSetting;
use App\Modules\Products\Enums\PriceTier;
use App\Modules\Products\Models\Category;
use App\Modules\Products\Models\Product;
use App\Modules\Products\Models\RawPriceDiscount;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class ProductPricingTest extends TestCase
{
    use RefreshDatabase;

    public function test_tier_prices_take_the_raw_percentage_from_persisted_configuration(): void
    {
        $product = $this->createProduct();
        RawPriceDiscount::query()->create(['pricing_mode' => PriceTier::Unit, 'amount' => 200]);
        OrderSetting::query()->where('key', OrderSettingKey::EmpanadaUnitSurchargePercent->value)->update(['integer_value' => 25]);
        OrderSetting::query()->where('key', OrderSettingKey::RawDiscountPercent->value)->update(['integer_value' => 10]);
        $prices = app(ProductPriceProvider::class);

        // D_raw = 18000 × 90% = 16200; U_raw = 16200 × 125 / 1200 = 1687.5 → 1700; half dozen = 6 × U.
        $this->assertSame(16200, $prices->priceFor($product->id, PriceTier::Dozen->value, false));
        $this->assertSame(1700, $prices->priceFor($product->id, PriceTier::Unit->value, false));
        $this->assertSame(10200, $prices->priceFor($product->id, PriceTier::HalfDozen->value, false));
        $this->assertSame(1800, $prices->priceFor($product->id, PriceTier::Unit->value, true));
        $this->assertSame(18000, $prices->priceFor($product->id, PriceTier::Dozen->value, true));
    }

    public function test_order_price_preview_ignores_client_price_and_uses_backend_product_price(): void
    {
        $admin = User::factory()->administrator()->create();
        $product = $this->createProduct();
        RawPriceDiscount::query()->create(['pricing_mode' => PriceTier::Dozen, 'amount' => 2000]);

        $this->actingAs($admin)->postJson(route('admin.orders.price-preview'), [
            'items' => [[
                'product_id' => $product->id,
                'pricing_mode' => PriceTier::Dozen->value,
                'quantity' => 2,
                'is_cooked' => true,
                'unit_price' => 1,
            ]],
        ])->assertOk()
            ->assertJsonPath('items.0.unit_price', 18000)
            ->assertJsonPath('items.0.line_total', 36000)
            ->assertJsonPath('total', 36000);
    }

    private function createProduct(): Product
    {
        $category = Category::query()->create([
            'name' => 'Especiales',
            'slug' => 'especiales',
            'sort_order' => 1,
            'is_active' => true,
        ]);

        return Product::query()->create([
            'category_id' => $category->id,
            'name' => 'Salteña',
            'slug' => 'saltena',
            'unit_price' => 1800,
            'half_dozen_price' => 10000,
            'dozen_price' => 18000,
            'is_available' => true,
            'sort_order' => 1,
        ]);
    }
}
