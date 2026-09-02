<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::rename('cooking_surcharges', 'raw_price_discounts');

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE raw_price_discounts RENAME CONSTRAINT cooking_surcharges_pricing_mode_valid TO raw_price_discounts_pricing_mode_valid');
            DB::statement('ALTER TABLE raw_price_discounts RENAME CONSTRAINT cooking_surcharges_amount_nonnegative TO raw_price_discounts_amount_nonnegative');
        }

        $this->transformProductPrices(addDiscounts: true);

        Schema::table('orders', function (Blueprint $table): void {
            $table->decimal('items_cooked_subtotal', 12, 2)->nullable()->after('cooking_surcharge');
            $table->decimal('raw_discount', 12, 2)->nullable()->after('items_cooked_subtotal');
        });

        Schema::table('order_items', function (Blueprint $table): void {
            $table->decimal('cooked_unit_price', 12, 2)->nullable()->after('cooking_surcharge');
            $table->decimal('cooked_line_total', 12, 2)->nullable()->after('cooked_unit_price');
            $table->decimal('raw_discount', 12, 2)->nullable()->after('cooked_line_total');
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE orders ADD CONSTRAINT orders_raw_pricing_breakdown_valid CHECK ((items_cooked_subtotal IS NULL AND raw_discount IS NULL) OR (items_cooked_subtotal IS NOT NULL AND raw_discount IS NOT NULL AND raw_discount >= 0 AND raw_discount <= items_cooked_subtotal AND items_subtotal = items_cooked_subtotal - raw_discount))');
            DB::statement('ALTER TABLE order_items ADD CONSTRAINT order_items_raw_pricing_breakdown_valid CHECK ((cooked_unit_price IS NULL AND cooked_line_total IS NULL AND raw_discount IS NULL) OR (cooked_unit_price IS NOT NULL AND cooked_line_total IS NOT NULL AND raw_discount IS NOT NULL AND cooked_unit_price >= 0 AND cooked_line_total = quantity * cooked_unit_price AND raw_discount >= 0 AND raw_discount <= cooked_line_total AND line_total = cooked_line_total - raw_discount))');
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE order_items DROP CONSTRAINT IF EXISTS order_items_raw_pricing_breakdown_valid');
            DB::statement('ALTER TABLE orders DROP CONSTRAINT IF EXISTS orders_raw_pricing_breakdown_valid');
        }

        Schema::table('order_items', function (Blueprint $table): void {
            $table->dropColumn(['cooked_unit_price', 'cooked_line_total', 'raw_discount']);
        });

        Schema::table('orders', function (Blueprint $table): void {
            $table->dropColumn(['items_cooked_subtotal', 'raw_discount']);
        });

        $this->transformProductPrices(addDiscounts: false);

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE raw_price_discounts RENAME CONSTRAINT raw_price_discounts_pricing_mode_valid TO cooking_surcharges_pricing_mode_valid');
            DB::statement('ALTER TABLE raw_price_discounts RENAME CONSTRAINT raw_price_discounts_amount_nonnegative TO cooking_surcharges_amount_nonnegative');
        }

        Schema::rename('raw_price_discounts', 'cooking_surcharges');
    }

    private function transformProductPrices(bool $addDiscounts): void
    {
        $discounts = DB::table('raw_price_discounts')
            ->pluck('amount', 'pricing_mode')
            ->map(fn (mixed $amount): int => (int) $amount);

        if (DB::table('products')->exists() && $discounts->count() !== 3) {
            throw new RuntimeException('Deben existir descuentos de precio crudo para cada modo antes de transformar los precios.');
        }

        foreach ([
            'unit' => 'unit_price',
            'half_dozen' => 'half_dozen_price',
            'dozen' => 'dozen_price',
        ] as $mode => $column) {
            $amount = $discounts->get($mode);

            if ($amount === null) {
                continue;
            }

            $addDiscounts
                ? DB::table('products')->increment($column, $amount)
                : DB::table('products')->decrement($column, $amount);
        }
    }
};
