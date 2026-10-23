<?php

namespace Tests\Feature\Products;

use App\Modules\Identity\Models\User;
use App\Modules\Orders\Enums\OrderCondition;
use App\Modules\Orders\Enums\PricingMode;
use App\Modules\Orders\Pricing\OrderPricingEngine;
use App\Modules\Products\Models\Category;
use App\Modules\Products\Models\Product;
use App\Modules\Products\Models\ProductSubcategory;
use App\Modules\Products\Pricing\EmpanadaUnitPrice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

final class ProductSubcategoryCatalogTest extends TestCase
{
    use RefreshDatabase;

    private Category $empanadas;

    private ProductSubcategory $classics;

    private ProductSubcategory $specials;

    protected function setUp(): void
    {
        parent::setUp();
        $this->empanadas = Category::query()->create(['name' => 'Empanadas', 'slug' => 'empanadas', 'sort_order' => 1, 'is_active' => true]);
        $this->classics = ProductSubcategory::query()->create(['category_id' => $this->empanadas->id, 'name' => 'Clásicas', 'slug' => 'clasicas', 'sort_order' => 1, 'is_active' => true]);
        $this->specials = ProductSubcategory::query()->create(['category_id' => $this->empanadas->id, 'name' => 'Especiales', 'slug' => 'especiales', 'sort_order' => 2, 'is_active' => true]);
    }

    public function test_the_public_catalog_exposes_each_product_subcategory_without_internal_ids(): void
    {
        $this->empanada('Carne', 18000, $this->classics, 1);
        $this->empanada('Cordero', 24000, $this->specials, 2);
        $this->empanada('Humita', 18000, null, 3);

        $this->getJson(route('public.products.index'))
            ->assertOk()
            ->assertJsonPath('data.0.subcategory', ['slug' => 'clasicas', 'name' => 'Clásicas', 'sort_order' => 1])
            ->assertJsonPath('data.1.subcategory', ['slug' => 'especiales', 'name' => 'Especiales', 'sort_order' => 2])
            ->assertJsonPath('data.2.subcategory', null)
            ->assertJsonMissingPath('data.0.subcategory.id')
            ->assertJsonMissingPath('data.0.subcategory_id');
    }

    public function test_an_inactive_subcategory_is_hidden_but_its_products_stay_on_the_public_menu(): void
    {
        $this->classics->update(['is_active' => false]);
        $this->empanada('Carne', 18000, $this->classics, 1);

        $this->getJson(route('public.products.index'))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Carne')
            ->assertJsonPath('data.0.subcategory', null);
    }

    public function test_deactivating_the_parent_category_hides_its_products_and_subcategories_and_reactivating_restores_them(): void
    {
        $product = $this->empanada('Carne', 18000, $this->classics, 1);
        $this->empanadas->update(['is_active' => false]);

        $this->getJson(route('public.products.index'))->assertOk()->assertJsonCount(0, 'data');
        $this->actingAs(User::factory()->administrator()->create())
            ->get(route('admin.home'))
            ->assertInertia(fn (Assert $page) => $page->has('products', 0));
        $this->assertSame($this->classics->id, $product->refresh()->subcategory_id);
        $this->assertTrue($this->classics->refresh()->is_active);

        $this->empanadas->update(['is_active' => true]);

        $this->getJson(route('public.products.index'))
            ->assertOk()
            ->assertJsonPath('data.0.subcategory.slug', 'clasicas');
    }

    public function test_the_admin_order_catalog_carries_the_active_subcategory(): void
    {
        $carne = $this->empanada('Carne', 18000, $this->classics, 1);
        $humita = $this->empanada('Humita', 18000, null, 2);

        $this->actingAs(User::factory()->administrator()->create())
            ->get(route('admin.home'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('products.0.id', $carne->id)
                ->where('products.0.category', ['id' => $this->empanadas->id, 'name' => 'Empanadas'])
                ->where('products.0.subcategory', ['id' => $this->classics->id, 'name' => 'Clásicas', 'sort_order' => 1])
                ->where('products.1.id', $humita->id)
                ->where('products.1.subcategory', null));
    }

    public function test_a_combined_dozen_mixes_subcategories_and_prices_exactly_as_without_them(): void
    {
        $carne = $this->empanada('Carne', 18000, $this->classics, 1);
        $cordero = $this->empanada('Cordero', 24000, $this->specials, 2);
        $items = [['product_id' => $carne->id, 'quantity' => 6], ['product_id' => $cordero->id, 'quantity' => 6]];

        $withSubcategories = app(OrderPricingEngine::class)->quote($items, OrderCondition::Cooked);
        Product::query()->update(['subcategory_id' => null]);
        $withoutSubcategories = app(OrderPricingEngine::class)->quote($items, OrderCondition::Cooked);

        $this->assertSame($withoutSubcategories->total, $withSubcategories->total);
        $this->assertSame([PricingMode::CombinedDozen->value], array_map(fn ($line) => $line->pricingMode, $withSubcategories->lines));
    }

    private function empanada(string $name, int $dozen, ?ProductSubcategory $subcategory, int $order): Product
    {
        $unit = EmpanadaUnitPrice::fromDozen($dozen, 10);

        return Product::query()->create([
            'category_id' => $this->empanadas->id,
            'subcategory_id' => $subcategory?->id,
            'pricing_family' => 'empanada',
            'name' => $name,
            'slug' => str($name)->slug()->toString(),
            'unit_price' => $unit,
            'half_dozen_price' => 6 * $unit,
            'dozen_price' => $dozen,
            'is_available' => true,
            'sort_order' => $order,
        ]);
    }
}
