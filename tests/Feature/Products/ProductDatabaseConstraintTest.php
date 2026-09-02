<?php

namespace Tests\Feature\Products;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class ProductDatabaseConstraintTest extends TestCase
{
    use RefreshDatabase;

    public function test_postgresql_enforces_nonnegative_catalog_order_and_raw_discount_values(): void
    {
        $constraints = DB::table('pg_constraint')
            ->whereIn('conname', [
                'categories_sort_order_nonnegative',
                'products_sort_order_nonnegative',
                'raw_price_discounts_amount_nonnegative',
            ])
            ->pluck('conname')
            ->all();

        $this->assertEqualsCanonicalizing([
            'categories_sort_order_nonnegative',
            'products_sort_order_nonnegative',
            'raw_price_discounts_amount_nonnegative',
        ], $constraints);
    }
}
