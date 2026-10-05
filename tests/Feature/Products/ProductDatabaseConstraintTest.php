<?php

namespace Tests\Feature\Products;

use App\Modules\Products\Models\Category;
use App\Modules\Products\Models\Product;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class ProductDatabaseConstraintTest extends TestCase
{
    use RefreshDatabase;

    public function test_pricing_family_migration_backfills_a_valid_legacy_product_as_empanada(): void
    {
        $migration = require base_path('database/migrations/2026_10_04_000000_add_product_pricing_family.php');
        $migration->down();

        $category = Category::query()->create(['name' => 'Catalog', 'slug' => 'catalog', 'sort_order' => 1, 'is_active' => true]);
        $product = Product::query()->create([
            'category_id' => $category->id,
            'name' => 'Legacy product',
            'slug' => 'legacy-product',
            'unit_price' => 1_000,
            'half_dozen_price' => 5_000,
            'dozen_price' => 9_000,
            'is_available' => true,
            'sort_order' => 1,
        ]);

        $migration->up();

        $this->assertSame('empanada', DB::table('products')->where('id', $product->id)->value('pricing_family'));
    }

    public function test_postgresql_rejects_invalid_and_null_product_pricing_families(): void
    {
        $category = Category::query()->create(['name' => 'Catalog', 'slug' => 'catalog', 'sort_order' => 1, 'is_active' => true]);
        $product = Product::query()->create([
            'category_id' => $category->id,
            'name' => 'Product',
            'slug' => 'product',
            'unit_price' => 1_000,
            'half_dozen_price' => 5_000,
            'dozen_price' => 9_000,
            'is_available' => true,
            'sort_order' => 1,
        ]);

        foreach (['unknown', null] as $family) {
            try {
                DB::transaction(fn (): int => DB::table('products')->whereKey($product->id)->update(['pricing_family' => $family]));
                $this->fail('PostgreSQL accepted an invalid pricing family.');
            } catch (QueryException) {
                $this->assertTrue(true);
            }
        }
    }

    public function test_postgresql_enforces_nonnegative_catalog_order_and_raw_discount_values(): void
    {
        $constraints = DB::table('pg_constraint')
            ->whereIn('conname', [
                'categories_sort_order_nonnegative',
                'products_sort_order_nonnegative',
                'raw_price_discounts_amount_nonnegative',
                'products_pricing_family_valid',
            ])
            ->pluck('conname')
            ->all();

        $this->assertEqualsCanonicalizing([
            'categories_sort_order_nonnegative',
            'products_sort_order_nonnegative',
            'raw_price_discounts_amount_nonnegative',
            'products_pricing_family_valid',
        ], $constraints);
    }
}
