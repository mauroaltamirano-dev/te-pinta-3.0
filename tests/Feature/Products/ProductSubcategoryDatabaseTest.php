<?php

namespace Tests\Feature\Products;

use App\Modules\Products\Models\Category;
use App\Modules\Products\Models\Product;
use App\Modules\Products\Models\ProductSubcategory;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Tests\TestCase;

final class ProductSubcategoryDatabaseTest extends TestCase
{
    use RefreshDatabase;

    private const MIGRATION = 'database/migrations/2026_10_23_000000_create_product_subcategories.php';

    public function test_the_migration_keeps_existing_products_and_categories_without_a_subcategory(): void
    {
        $migration = require base_path(self::MIGRATION);
        $migration->down();

        $category = $this->category('Empanadas');
        $product = $this->product($category, 'Carne');
        $before = DB::table('products')->where('id', $product->id)->first();

        $migration->up();

        $after = DB::table('products')->where('id', $product->id)->first();
        $this->assertNull($after->subcategory_id);
        $this->assertSame($before->category_id, $after->category_id);
        $this->assertSame($before->dozen_price, $after->dozen_price);
        $this->assertSame(1, DB::table('categories')->count());
        $this->assertSame(1, DB::table('products')->count());
    }

    public function test_postgresql_rejects_a_subcategory_from_another_category(): void
    {
        $empanadas = $this->category('Empanadas');
        $pizzas = $this->category('Pizzas');
        $pizzaSpecials = ProductSubcategory::query()->create(['category_id' => $pizzas->id, 'name' => 'Especiales', 'slug' => 'especiales', 'sort_order' => 1, 'is_active' => true]);
        $product = $this->product($empanadas, 'Carne');

        $this->expectException(QueryException::class);
        DB::table('products')->where('id', $product->id)->update(['subcategory_id' => $pizzaSpecials->id]);
    }

    public function test_postgresql_accepts_a_subcategory_of_the_same_category_and_a_null_one(): void
    {
        $empanadas = $this->category('Empanadas');
        $classics = ProductSubcategory::query()->create(['category_id' => $empanadas->id, 'name' => 'Clásicas', 'slug' => 'clasicas', 'sort_order' => 1, 'is_active' => true]);
        $product = $this->product($empanadas, 'Carne');

        DB::table('products')->where('id', $product->id)->update(['subcategory_id' => $classics->id]);
        $this->assertSame($classics->id, $product->refresh()->subcategory_id);

        DB::table('products')->where('id', $product->id)->update(['subcategory_id' => null]);
        $this->assertNull($product->refresh()->subcategory_id);
    }

    public function test_the_same_subcategory_name_and_slug_can_exist_under_different_categories(): void
    {
        $empanadas = $this->category('Empanadas');
        $pizzas = $this->category('Pizzas');
        ProductSubcategory::query()->create(['category_id' => $empanadas->id, 'name' => 'Especiales', 'slug' => 'especiales', 'sort_order' => 1, 'is_active' => true]);
        ProductSubcategory::query()->create(['category_id' => $pizzas->id, 'name' => 'Especiales', 'slug' => 'especiales', 'sort_order' => 1, 'is_active' => true]);

        $this->assertSame(2, DB::table('product_subcategories')->where('slug', 'especiales')->count());
    }

    public function test_postgresql_rejects_the_same_name_in_one_category_ignoring_case(): void
    {
        $empanadas = $this->category('Empanadas');
        ProductSubcategory::query()->create(['category_id' => $empanadas->id, 'name' => 'Especiales', 'slug' => 'especiales', 'sort_order' => 1, 'is_active' => true]);

        $this->assertConstraintViolation('product_subcategories_name_unique', fn () => ProductSubcategory::query()->create(['category_id' => $empanadas->id, 'name' => 'ESPECIALES', 'slug' => 'especiales-2', 'sort_order' => 2, 'is_active' => true]));
    }

    public function test_postgresql_rejects_the_same_slug_in_one_category(): void
    {
        $empanadas = $this->category('Empanadas');
        ProductSubcategory::query()->create(['category_id' => $empanadas->id, 'name' => 'Especiales', 'slug' => 'especiales', 'sort_order' => 1, 'is_active' => true]);

        $this->assertConstraintViolation('product_subcategories_slug_unique', fn () => ProductSubcategory::query()->create(['category_id' => $empanadas->id, 'name' => 'Especiales 2', 'slug' => 'especiales', 'sort_order' => 2, 'is_active' => true]));
    }

    public function test_postgresql_rejects_a_negative_subcategory_order(): void
    {
        $category = $this->category('Bebidas');

        $this->expectException(QueryException::class);
        ProductSubcategory::query()->create(['category_id' => $category->id, 'name' => 'Sin alcohol', 'slug' => 'sin-alcohol', 'sort_order' => -1, 'is_active' => true]);
    }

    public function test_a_subcategory_row_belongs_to_the_current_restaurant(): void
    {
        $category = $this->category('Bebidas');
        $subcategory = ProductSubcategory::query()->create(['category_id' => $category->id, 'name' => 'Con alcohol', 'slug' => 'con-alcohol', 'sort_order' => 1, 'is_active' => true]);

        $this->assertSame(
            DB::table('categories')->where('id', $category->id)->value('restaurant_id'),
            DB::table('product_subcategories')->where('id', $subcategory->id)->value('restaurant_id'),
        );
    }

    public function test_rollback_is_refused_once_subcategories_exist(): void
    {
        $category = $this->category('Bebidas');
        ProductSubcategory::query()->create(['category_id' => $category->id, 'name' => 'Con alcohol', 'slug' => 'con-alcohol', 'sort_order' => 1, 'is_active' => true]);
        $migration = require base_path(self::MIGRATION);

        try {
            $migration->down();
            $this->fail('The rollback dropped existing subcategories.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('subcategories', $exception->getMessage());
        }

        $this->assertSame(1, DB::table('product_subcategories')->count());
    }

    private function category(string $name): Category
    {
        return Category::query()->create(['name' => $name, 'slug' => str($name)->slug()->toString(), 'sort_order' => 1, 'is_active' => true]);
    }

    private function product(Category $category, string $name): Product
    {
        return Product::query()->create([
            'category_id' => $category->id,
            'name' => $name,
            'slug' => str($name)->slug()->toString(),
            'unit_price' => 2_000,
            'half_dozen_price' => 12_000,
            'dozen_price' => 20_000,
            'is_available' => true,
            'sort_order' => 1,
        ]);
    }

    private function assertConstraintViolation(string $constraint, callable $write): void
    {
        try {
            $write();
        } catch (QueryException $exception) {
            $this->assertStringContainsString($constraint, $exception->getMessage());

            return;
        }

        $this->fail("Expected a violation of {$constraint}.");
    }
}
