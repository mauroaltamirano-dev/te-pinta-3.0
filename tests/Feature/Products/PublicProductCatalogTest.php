<?php

namespace Tests\Feature\Products;

use App\Modules\Products\Models\Category;
use App\Modules\Products\Models\Product;
use App\Modules\Products\Models\Tag;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class PublicProductCatalogTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_catalog_only_exposes_available_products_from_active_categories(): void
    {
        $activeCategory = $this->createCategory('Clásicas', 'clasicas', true, 1);
        $inactiveCategory = $this->createCategory('Archived', 'archived', false, 2);
        $vegetarian = Tag::query()->create(['name' => 'Vegetariana', 'slug' => 'vegetariana']);

        $available = $this->createProduct($activeCategory, 'Caprese', 'caprese', true, 1);
        $available->tags()->attach($vegetarian);
        $this->createProduct($activeCategory, 'Unavailable', 'unavailable', false, 2);
        $deleted = $this->createProduct($activeCategory, 'Deleted', 'deleted', true, 3);
        $deleted->delete();
        $this->createProduct($inactiveCategory, 'Hidden category', 'hidden-category', true, 1);

        $response = $this->getJson(route('public.products.index'));

        $response->assertOk()
            ->assertJsonPath('data.0.name', 'Caprese')
            ->assertJsonPath('data.0.category.slug', 'clasicas')
            ->assertJsonPath('data.0.tags.0.slug', 'vegetariana')
            ->assertJsonPath('data.0.prices.unit', 1600)
            ->assertJsonCount(1, 'data')
            ->assertJsonCount(1, 'categories')
            ->assertJsonMissing(['name' => 'Unavailable'])
            ->assertJsonMissing(['name' => 'Deleted'])
            ->assertJsonMissing(['name' => 'Hidden category'])
            ->assertJsonMissingPath('data.0.id')
            ->assertJsonMissingPath('data.0.image_path')
            ->assertJsonMissingPath('data.0.deleted_at')
            ->assertJsonMissingPath('data.0.is_available');
    }

    public function test_public_catalog_respects_manual_category_and_product_order(): void
    {
        $second = $this->createCategory('Second', 'second', true, 20);
        $first = $this->createCategory('First', 'first', true, 10);
        $this->createProduct($first, 'Later', 'later', true, 30);
        $this->createProduct($first, 'Earlier', 'earlier', true, 10);
        $this->createProduct($second, 'Last category', 'last-category', true, 1);

        $response = $this->getJson(route('public.products.index'))->assertOk();

        $this->assertSame(['First', 'Second'], $response->json('categories.*.name'));
        $this->assertSame(['Earlier', 'Later', 'Last category'], $response->json('data.*.name'));
    }

    private function createCategory(string $name, string $slug, bool $active, int $order): Category
    {
        return Category::query()->create([
            'name' => $name,
            'slug' => $slug,
            'is_active' => $active,
            'sort_order' => $order,
        ]);
    }

    private function createProduct(
        Category $category,
        string $name,
        string $slug,
        bool $available,
        int $order,
    ): Product {
        return Product::query()->create([
            'category_id' => $category->id,
            'name' => $name,
            'slug' => $slug,
            'unit_price' => 1600,
            'half_dozen_price' => 9000,
            'dozen_price' => 16000,
            'is_available' => $available,
            'sort_order' => $order,
        ]);
    }
}
