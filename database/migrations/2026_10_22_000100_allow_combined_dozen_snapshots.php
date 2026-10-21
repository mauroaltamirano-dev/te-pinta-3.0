<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Combined dozen by percentage. A version-2 mixed dozen is one whole-peso line of
 * twelve units without a product (its flavors live in order_item_compositions);
 * unit and dozen lines keep the fixed-dozen rule unchanged. The widened check is a
 * superset of the previous one, so every stored version-2 row stays valid.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::unprepared(<<<'SQL'
ALTER TABLE order_items DROP CONSTRAINT order_items_fixed_dozen_snapshot_valid;
ALTER TABLE order_items ADD CONSTRAINT order_items_fixed_dozen_snapshot_valid CHECK (
 pricing_snapshot_version = 1
 OR (((pricing_mode IN ('unit', 'dozen') AND product_id IS NOT NULL)
   OR (pricing_mode = 'combined_dozen' AND product_id IS NULL AND quantity = 1 AND units_count = 12))
  AND cooked_unit_price IS NOT NULL AND cooked_line_total IS NOT NULL AND raw_discount IS NOT NULL
  AND applied_exact_numerator_12 = line_total * 12
  AND cooked_exact_numerator_12 = cooked_line_total * 12
  AND raw_discount_exact_numerator_12 = raw_discount * 12
  AND surcharge_exact_numerator_12 = 0));
SQL);
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }
        // Restoring the narrow check would reject stored mixed dozens; never drop that history silently.
        if (DB::table('order_items')->where('pricing_snapshot_version', 2)->where('pricing_mode', 'combined_dozen')->exists()) {
            throw new RuntimeException('Cannot restore the fixed-dozen snapshot check while version-2 combined dozen history exists.');
        }

        DB::unprepared(<<<'SQL'
ALTER TABLE order_items DROP CONSTRAINT order_items_fixed_dozen_snapshot_valid;
ALTER TABLE order_items ADD CONSTRAINT order_items_fixed_dozen_snapshot_valid CHECK (
 pricing_snapshot_version = 1
 OR (pricing_mode IN ('unit', 'dozen') AND product_id IS NOT NULL
  AND cooked_unit_price IS NOT NULL AND cooked_line_total IS NOT NULL AND raw_discount IS NOT NULL
  AND applied_exact_numerator_12 = line_total * 12
  AND cooked_exact_numerator_12 = cooked_line_total * 12
  AND raw_discount_exact_numerator_12 = raw_discount * 12
  AND surcharge_exact_numerator_12 = 0));
SQL);
    }
};
