<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE categories ADD CONSTRAINT categories_sort_order_nonnegative CHECK (sort_order >= 0)');
        DB::statement('ALTER TABLE products ADD CONSTRAINT products_sort_order_nonnegative CHECK (sort_order >= 0)');
        DB::statement('ALTER TABLE cooking_surcharges ADD CONSTRAINT cooking_surcharges_amount_nonnegative CHECK (amount >= 0)');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE cooking_surcharges DROP CONSTRAINT IF EXISTS cooking_surcharges_amount_nonnegative');
        DB::statement('ALTER TABLE products DROP CONSTRAINT IF EXISTS products_sort_order_nonnegative');
        DB::statement('ALTER TABLE categories DROP CONSTRAINT IF EXISTS categories_sort_order_nonnegative');
    }
};
