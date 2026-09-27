<?php

namespace Tests\Feature\Products;

use App\Modules\Products\Models\Category;
use App\Modules\Products\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

final class PublicProductDetailTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    public function test_detail_exposes_editorial_metadata_prices_availability_and_server_rendered_open_graph(): void
    {
        $category = Category::query()->create(['name' => 'Clásicas', 'slug' => 'clasicas', 'is_active' => true]);
        $product = $this->product($category, 'caprese', false);
        $product->update([
            'description' => 'Tomate y albahaca.',
            'long_description' => 'Preparada todos los días.',
            'public_ingredients' => 'Tomate, albahaca, mozzarella.',
        ]);

        $this->get(route('public.products.show', ['slug' => 'caprese']))
            ->assertOk()
            ->assertSee('<meta data-public-seo property="og:title" content="Caprese | Te Pinta">', false)
            ->assertSee('<link data-public-seo rel="canonical" href="'.route('public.products.show', ['slug' => 'caprese']).'">', false)
            ->assertInertia(fn (Assert $page) => $page
                ->component('public/product-detail')
                ->where('product.slug', 'caprese')
                ->where('product.is_available', false)
                ->where('product.long_description', 'Preparada todos los días.')
                ->where('product.public_ingredients', 'Tomate, albahaca, mozzarella.')
                ->where('product.prices.half_dozen', 9000)
                ->missing('product.inventoryConsumptionDefinition'));
    }

    public function test_detail_hides_inactive_categories_deleted_products_and_unknown_slugs(): void
    {
        $active = Category::query()->create(['name' => 'Clásicas', 'slug' => 'clasicas', 'is_active' => true]);
        $inactive = Category::query()->create(['name' => 'Ocultas', 'slug' => 'ocultas', 'is_active' => false]);
        $this->product($inactive, 'hidden', true);
        $deleted = $this->product($active, 'deleted', true);
        $deleted->delete();

        $this->get('/menu/hidden')->assertNotFound();
        $this->get('/menu/deleted')->assertNotFound();
        $this->get('/menu/unknown')->assertNotFound();
    }

    private function product(Category $category, string $slug, bool $available): Product
    {
        return Product::query()->create([
            'category_id' => $category->id,
            'name' => ucfirst($slug),
            'slug' => $slug,
            'is_available' => $available,
            'unit_price' => 1600,
            'half_dozen_price' => 9000,
            'dozen_price' => 16000,
        ]);
    }
}
