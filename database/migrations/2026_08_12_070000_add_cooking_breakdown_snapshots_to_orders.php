<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            // Nullable by design: old orders did not snapshot the raw equivalent.
            $table->decimal('items_base_subtotal', 12, 2)->nullable()->after('notes');
            $table->decimal('cooking_surcharge', 12, 2)->nullable()->after('items_base_subtotal');
        });

        Schema::table('order_items', function (Blueprint $table): void {
            // Nullable by design: rebuilding history from current prices would be incorrect.
            $table->decimal('base_unit_price', 12, 2)->nullable()->after('unit_price');
            $table->decimal('base_line_total', 12, 2)->nullable()->after('line_total');
            $table->decimal('cooking_surcharge', 12, 2)->nullable()->after('base_line_total');
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE orders ADD CONSTRAINT orders_cooking_breakdown_valid CHECK ((items_base_subtotal IS NULL AND cooking_surcharge IS NULL) OR (items_base_subtotal IS NOT NULL AND cooking_surcharge IS NOT NULL AND items_base_subtotal >= 0 AND items_subtotal = items_base_subtotal + cooking_surcharge))');
            DB::statement('ALTER TABLE order_items ADD CONSTRAINT order_items_cooking_breakdown_valid CHECK ((base_unit_price IS NULL AND base_line_total IS NULL AND cooking_surcharge IS NULL) OR (base_unit_price IS NOT NULL AND base_line_total IS NOT NULL AND cooking_surcharge IS NOT NULL AND base_unit_price >= 0 AND base_line_total = quantity * base_unit_price AND line_total = base_line_total + cooking_surcharge))');
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE order_items DROP CONSTRAINT IF EXISTS order_items_cooking_breakdown_valid');
            DB::statement('ALTER TABLE orders DROP CONSTRAINT IF EXISTS orders_cooking_breakdown_valid');
        }

        Schema::table('order_items', function (Blueprint $table): void {
            $table->dropColumn(['base_unit_price', 'base_line_total', 'cooking_surcharge']);
        });

        Schema::table('orders', function (Blueprint $table): void {
            $table->dropColumn(['items_base_subtotal', 'cooking_surcharge']);
        });
    }
};
