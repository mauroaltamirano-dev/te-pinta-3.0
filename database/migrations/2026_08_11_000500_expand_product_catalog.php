<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('categories', function (Blueprint $table): void {
            $table->unsignedInteger('sort_order')->default(0)->after('slug');
            $table->index(['is_active', 'sort_order']);
        });

        Schema::create('tags', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->timestamps();
        });

        Schema::create('product_tag', function (Blueprint $table): void {
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tag_id')->constrained()->restrictOnDelete();
            $table->primary(['product_id', 'tag_id']);
        });

        Schema::table('products', function (Blueprint $table): void {
            $table->unsignedInteger('sort_order')->default(0)->after('image_path');
        });

        DB::statement('ALTER TABLE products DROP CONSTRAINT IF EXISTS products_prices_nonnegative');
        Schema::table('products', function (Blueprint $table): void {
            $table->dropIndex('products_category_id_is_active_index');
            $table->renameColumn('price_unit', 'unit_price');
            $table->renameColumn('price_half_dozen', 'half_dozen_price');
            $table->renameColumn('price_dozen', 'dozen_price');
            $table->renameColumn('is_active', 'is_available');
        });

        DB::statement('ALTER TABLE products ALTER COLUMN unit_price TYPE BIGINT USING ROUND(unit_price)::BIGINT');
        DB::statement('ALTER TABLE products ALTER COLUMN half_dozen_price TYPE BIGINT USING ROUND(half_dozen_price)::BIGINT');
        DB::statement('ALTER TABLE products ALTER COLUMN dozen_price TYPE BIGINT USING ROUND(dozen_price)::BIGINT');
        DB::statement('ALTER TABLE products ADD CONSTRAINT products_prices_nonnegative CHECK (unit_price >= 0 AND half_dozen_price >= 0 AND dozen_price >= 0)');

        $vegetarianProductIds = DB::table('products')->where('is_vegetarian', true)->pluck('id');

        if ($vegetarianProductIds->isNotEmpty()) {
            $tagId = DB::table('tags')->insertGetId([
                'name' => 'Vegetariana',
                'slug' => 'vegetariana',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('product_tag')->insert(
                $vegetarianProductIds->map(fn (int $productId): array => [
                    'product_id' => $productId,
                    'tag_id' => $tagId,
                ])->all(),
            );
        }

        Schema::table('products', function (Blueprint $table): void {
            $table->dropColumn('is_vegetarian');
            $table->index(['category_id', 'sort_order']);
            $table->index(['category_id', 'is_available', 'sort_order']);
        });

        Schema::create('cooking_surcharges', function (Blueprint $table): void {
            $table->id();
            $table->string('pricing_mode')->unique();
            $table->unsignedBigInteger('amount');
            $table->timestamps();
        });

        DB::statement("ALTER TABLE cooking_surcharges ADD CONSTRAINT cooking_surcharges_pricing_mode_valid CHECK (pricing_mode IN ('unit', 'half_dozen', 'dozen'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('cooking_surcharges');

        Schema::table('products', function (Blueprint $table): void {
            $table->boolean('is_vegetarian')->default(false);
            $table->dropIndex(['category_id', 'sort_order']);
            $table->dropIndex(['category_id', 'is_available', 'sort_order']);
        });

        $vegetarianTagId = DB::table('tags')->where('slug', 'vegetariana')->value('id');

        if (is_int($vegetarianTagId)) {
            DB::table('products')
                ->whereIn('id', DB::table('product_tag')->where('tag_id', $vegetarianTagId)->select('product_id'))
                ->update(['is_vegetarian' => true]);
        }

        Schema::dropIfExists('product_tag');
        Schema::dropIfExists('tags');

        DB::statement('ALTER TABLE products DROP CONSTRAINT IF EXISTS products_prices_nonnegative');
        DB::statement('ALTER TABLE products ALTER COLUMN unit_price TYPE NUMERIC(12, 2) USING unit_price::NUMERIC(12, 2)');
        DB::statement('ALTER TABLE products ALTER COLUMN half_dozen_price TYPE NUMERIC(12, 2) USING half_dozen_price::NUMERIC(12, 2)');
        DB::statement('ALTER TABLE products ALTER COLUMN dozen_price TYPE NUMERIC(12, 2) USING dozen_price::NUMERIC(12, 2)');

        Schema::table('products', function (Blueprint $table): void {
            $table->renameColumn('unit_price', 'price_unit');
            $table->renameColumn('half_dozen_price', 'price_half_dozen');
            $table->renameColumn('dozen_price', 'price_dozen');
            $table->renameColumn('is_available', 'is_active');
            $table->dropColumn('sort_order');
            $table->index(['category_id', 'is_active']);
        });

        DB::statement('ALTER TABLE products ADD CONSTRAINT products_prices_nonnegative CHECK (price_unit >= 0 AND price_half_dozen >= 0 AND price_dozen >= 0)');

        Schema::table('categories', function (Blueprint $table): void {
            $table->dropIndex(['is_active', 'sort_order']);
            $table->dropColumn('sort_order');
        });
    }
};
