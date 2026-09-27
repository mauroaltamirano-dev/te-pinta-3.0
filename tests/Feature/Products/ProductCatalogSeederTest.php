<?php

namespace Tests\Feature\Products;

use App\Modules\Products\Enums\PriceTier;
use App\Modules\Products\Models\Product;
use App\Modules\Products\Models\RawPriceDiscount;
use Database\Seeders\ProductCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

final class ProductCatalogSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeder_creates_the_exact_initial_catalog_and_is_repeatable(): void
    {
        $this->seed(ProductCatalogSeeder::class);
        $this->seed(ProductCatalogSeeder::class);

        $this->assertDatabaseCount('categories', 2);
        $this->assertDatabaseCount('tags', 1);
        $this->assertDatabaseCount('products', 9);
        $this->assertDatabaseCount('raw_price_discounts', 3);
        $this->assertDatabaseHas('categories', ['name' => 'Clásicas', 'slug' => 'clasicas']);
        $this->assertDatabaseHas('categories', ['name' => 'Especiales', 'slug' => 'especiales']);
        $this->assertDatabaseHas('tags', ['name' => 'Vegetariana', 'slug' => 'vegetariana']);

        $expected = [
            'Salteña' => [1800, 10000, 18000, 'especiales', false],
            'Humita Tucumana' => [1700, 9500, 17000, 'especiales', true],
            'Hongos al puerro' => [1800, 10000, 18000, 'especiales', true],
            'Pollo y portobellos' => [1800, 10000, 18000, 'especiales', false],
            'Matambre y verduras asadas' => [1900, 10500, 19000, 'especiales', false],
            'Criolla Salada' => [1800, 10000, 18000, 'clasicas', false],
            'Criolla Dulce' => [1800, 10000, 18000, 'clasicas', false],
            'Jamón y Queso' => [1600, 9000, 16000, 'clasicas', false],
            'Caprese' => [1600, 9000, 16000, 'clasicas', true],
        ];

        foreach ($expected as $name => [$unit, $halfDozen, $dozen, $categorySlug, $vegetarian]) {
            $product = Product::query()->with(['category', 'tags'])->where('name', $name)->sole();
            $this->assertSame([$unit, $halfDozen, $dozen], [
                $product->unit_price,
                $product->half_dozen_price,
                $product->dozen_price,
            ]);
            $this->assertSame($categorySlug, $product->category->slug);
            $this->assertSame($vegetarian, $product->tags->contains('slug', 'vegetariana'));
            $this->assertTrue($product->is_available);
            $this->assertNull($product->description);
            $this->assertNull($product->image_path);
        }

        $this->assertSame(
            ['Salteña', 'Humita Tucumana', 'Hongos al puerro', 'Pollo y portobellos', 'Matambre y verduras asadas'],
            Product::query()
                ->whereHas('category', fn ($query) => $query->where('slug', 'especiales'))
                ->orderBy('sort_order')
                ->pluck('name')
                ->all(),
        );
        $this->assertSame(
            ['Criolla Salada', 'Criolla Dulce', 'Jamón y Queso', 'Caprese'],
            Product::query()
                ->whereHas('category', fn ($query) => $query->where('slug', 'clasicas'))
                ->orderBy('sort_order')
                ->pluck('name')
                ->all(),
        );

        $this->assertSame(200, $this->discount(PriceTier::Unit));
        $this->assertSame(1000, $this->discount(PriceTier::HalfDozen));
        $this->assertSame(2000, $this->discount(PriceTier::Dozen));
    }

    public function test_reseeding_never_overwrites_admin_changes_or_resurrects_deleted_catalog_records(): void
    {
        Storage::fake('public');
        $this->seed(ProductCatalogSeeder::class);

        $product = Product::query()->where('slug', 'saltena')->sole();
        $vegetarian = $product->tags()->getRelated()->newQuery()->where('slug', 'vegetariana')->sole();
        Storage::disk('public')->put('products/admin-image.jpg', 'image');
        $product->update([
            'unit_price' => 9999,
            'is_available' => false,
            'image_path' => 'products/admin-image.jpg',
        ]);
        $product->tags()->attach($vegetarian);

        $category = $product->category;
        $category->update(['sort_order' => 77, 'is_active' => false]);
        RawPriceDiscount::query()->where('pricing_mode', PriceTier::Unit)->update(['amount' => 725]);

        $deleted = Product::query()->where('slug', 'caprese')->sole();
        $deleted->delete();

        $this->seed(ProductCatalogSeeder::class);

        $product->refresh();
        $this->assertSame(9999, $product->unit_price);
        $this->assertFalse($product->is_available);
        $this->assertSame('products/admin-image.jpg', $product->image_path);
        $this->assertTrue($product->tags->contains('slug', 'vegetariana'));
        $this->assertSame(77, $category->refresh()->sort_order);
        $this->assertFalse($category->is_active);
        $this->assertSame(725, $this->discount(PriceTier::Unit));
        $this->assertTrue(Product::query()->withTrashed()->where('slug', 'caprese')->sole()->trashed());
        $this->assertSame(9, Product::query()->withTrashed()->count());
        Storage::disk('public')->assertExists('products/admin-image.jpg');
    }

    public function test_renaming_a_starter_product_does_not_recreate_it_on_reseed(): void
    {
        $this->seed(ProductCatalogSeeder::class);

        Product::query()->where('slug', 'saltena')->sole()->update([
            'name' => 'Salteña de la casa',
            'slug' => 'saltena-de-la-casa',
        ]);

        $this->seed(ProductCatalogSeeder::class);

        $this->assertSame(9, Product::query()->withTrashed()->count());
        $this->assertDatabaseMissing('products', ['slug' => 'saltena']);
        $this->assertDatabaseHas('products', ['slug' => 'saltena-de-la-casa']);
    }

    public function test_soft_deleted_catalog_is_not_bootstrapped_again(): void
    {
        $this->seed(ProductCatalogSeeder::class);
        Product::query()->delete();

        $this->seed(ProductCatalogSeeder::class);

        $this->assertSame(9, Product::query()->withTrashed()->count());
        $this->assertSame(0, Product::query()->count());
    }

    private function discount(PriceTier $tier): int
    {
        return RawPriceDiscount::query()->where('pricing_mode', $tier)->sole()->amount;
    }
}
