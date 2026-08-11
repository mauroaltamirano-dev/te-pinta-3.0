<?php

namespace Tests\Feature\Products;

use App\Modules\Identity\Models\User;
use App\Modules\Products\Actions\CalculateProductPrice;
use App\Modules\Products\Enums\PriceTier;
use App\Modules\Products\Models\Category;
use App\Modules\Products\Models\CookingSurcharge;
use App\Modules\Products\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class ProductPricingTest extends TestCase
{
    use RefreshDatabase;

    public function test_cooking_surcharge_is_read_from_persisted_global_configuration(): void
    {
        $product = $this->createProduct();
        CookingSurcharge::query()->create(['pricing_mode' => PriceTier::Unit, 'amount' => 200]);
        $calculator = app(CalculateProductPrice::class);

        $this->assertSame(1600, $calculator->execute($product, PriceTier::Unit, false));
        $this->assertSame(1800, $calculator->execute($product, PriceTier::Unit, true));

        CookingSurcharge::query()->where('pricing_mode', PriceTier::Unit)->update(['amount' => 350]);

        $this->assertSame(1950, $calculator->execute($product, PriceTier::Unit, true));
    }

    public function test_order_price_preview_ignores_client_price_and_uses_backend_product_price(): void
    {
        $admin = User::factory()->administrator()->create();
        $product = $this->createProduct();
        CookingSurcharge::query()->create(['pricing_mode' => PriceTier::Dozen, 'amount' => 2000]);

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
            'unit_price' => 1600,
            'half_dozen_price' => 9000,
            'dozen_price' => 16000,
            'is_available' => true,
            'sort_order' => 1,
        ]);
    }
}
