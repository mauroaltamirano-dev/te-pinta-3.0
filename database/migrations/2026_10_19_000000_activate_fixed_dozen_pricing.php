<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Fixed dozen pricing. Empanada unit prices are derived from each flavor's dozen
 * (U = D / 12 × (1 + surcharge%), rounded half-up to ARS 100) and stored; version-2
 * snapshots hold whole-peso dozens and units plus one ARS 100 rounding of the final
 * total. Every version-1 rule is kept verbatim for historical orders.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }
        // Reserved version-2 rows predate these rules; never reinterpret them silently.
        if (DB::table('orders')->where('pricing_snapshot_version', '<>', 1)->exists()
            || DB::table('order_items')->where('pricing_snapshot_version', '<>', 1)->exists()) {
            throw new RuntimeException('Reserved version-2 pricing rows already exist; inspect them before activating fixed dozen pricing.');
        }

        DB::table('order_settings')->insertOrIgnore(['key' => 'empanada_unit_surcharge_percent', 'integer_value' => 10, 'string_value' => null, 'created_at' => now(), 'updated_at' => now()]);
        $percent = (int) DB::table('order_settings')->where('key', 'empanada_unit_surcharge_percent')->value('integer_value');
        // One-time derivation of the stored unit price, soft-deleted flavors included. Historical orders keep their snapshots.
        DB::update(
            "update products set unit_price = ((dozen_price::bigint * ? + 60000) / 120000) * 100, updated_at = now() where pricing_family = 'empanada'",
            [100 + $percent],
        );

        DB::unprepared(<<<'SQL'
ALTER TABLE order_items ADD CONSTRAINT order_items_fixed_dozen_snapshot_valid CHECK (
 pricing_snapshot_version = 1
 OR (pricing_mode IN ('unit', 'dozen') AND product_id IS NOT NULL
  AND cooked_unit_price IS NOT NULL AND cooked_line_total IS NOT NULL AND raw_discount IS NOT NULL
  AND applied_exact_numerator_12 = line_total * 12
  AND cooked_exact_numerator_12 = cooked_line_total * 12
  AND raw_discount_exact_numerator_12 = raw_discount * 12
  AND surcharge_exact_numerator_12 = 0));

ALTER TABLE orders ADD CONSTRAINT orders_fixed_dozen_products_valid CHECK (
 pricing_snapshot_version = 1
 OR (items_cooked_subtotal IS NOT NULL AND raw_discount IS NOT NULL
  AND product_exact_numerator_12 = items_subtotal * 12
  AND cooked_product_exact_numerator_12 = items_cooked_subtotal * 12
  AND raw_discount_exact_numerator_12 = raw_discount * 12));

ALTER TABLE orders DROP CONSTRAINT orders_total_matches_breakdown;
ALTER TABLE orders ADD CONSTRAINT orders_total_matches_breakdown CHECK (
 (pricing_snapshot_version = 1 AND total = items_subtotal + toppings_subtotal + shipping_fee_snapshot - discount_amount)
 OR (pricing_snapshot_version = 2 AND total % 100 = 0
  AND net_pre_round_exact_numerator_1200 = product_exact_numerator_12 * 100 + toppings_subtotal * 1200
   - discount_exact_numerator_1200 + shipping_fee_snapshot * 1200
  AND total * 1200 = net_pre_round_exact_numerator_1200 + rounding_adjustment_numerator_1200
  AND rounding_adjustment_numerator_1200 > -60000 AND rounding_adjustment_numerator_1200 <= 60000));

ALTER TABLE orders DROP CONSTRAINT orders_discount_valid;
ALTER TABLE orders ADD CONSTRAINT orders_discount_valid CHECK (
 (pricing_snapshot_version = 1 AND (
  (discount_type IS NULL AND discount_value IS NULL AND discount_amount = 0)
  OR (discount_type = 'percentage' AND discount_value BETWEEN 0 AND 100
   AND discount_amount = LEAST(ROUND((items_subtotal + toppings_subtotal) * discount_value / 100.0), items_subtotal + toppings_subtotal))
  OR (discount_type = 'fixed_amount' AND discount_value >= 0
   AND discount_amount = LEAST(discount_value, items_subtotal + toppings_subtotal))))
 OR (pricing_snapshot_version = 2
  AND discount_amount = ROUND(discount_exact_numerator_1200 / 1200.0)
  AND (
   (discount_type IS NULL AND discount_value IS NULL AND discount_exact_numerator_1200 = 0)
   OR (discount_type = 'percentage' AND discount_value BETWEEN 0 AND 100
    AND discount_exact_numerator_1200 * 100 = (product_exact_numerator_12 * 100 + toppings_subtotal * 1200) * discount_value)
   OR (discount_type = 'fixed_amount' AND discount_value >= 0
    AND discount_exact_numerator_1200 = LEAST(discount_value::bigint * 1200, product_exact_numerator_12 * 100 + toppings_subtotal * 1200)))));
SQL);
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }
        if (DB::table('orders')->where('pricing_snapshot_version', '<>', 1)->exists()
            || DB::table('order_items')->where('pricing_snapshot_version', '<>', 1)->exists()) {
            throw new RuntimeException('Cannot restore legacy pricing checks while version-2 commercial history exists.');
        }

        // The surcharge setting and the derived unit prices stay: they are valid catalog data.
        DB::unprepared(<<<'SQL'
ALTER TABLE order_items DROP CONSTRAINT order_items_fixed_dozen_snapshot_valid;
ALTER TABLE orders DROP CONSTRAINT orders_fixed_dozen_products_valid;
ALTER TABLE orders DROP CONSTRAINT orders_total_matches_breakdown;
ALTER TABLE orders ADD CONSTRAINT orders_total_matches_breakdown CHECK (total = items_subtotal + toppings_subtotal + shipping_fee_snapshot - discount_amount);
ALTER TABLE orders DROP CONSTRAINT orders_discount_valid;
ALTER TABLE orders ADD CONSTRAINT orders_discount_valid CHECK (
 (discount_type IS NULL AND discount_value IS NULL AND discount_amount = 0)
 OR (discount_type = 'percentage' AND discount_value BETWEEN 0 AND 100
  AND discount_amount = LEAST(ROUND((items_subtotal + toppings_subtotal) * discount_value / 100.0), items_subtotal + toppings_subtotal))
 OR (discount_type = 'fixed_amount' AND discount_value >= 0
  AND discount_amount = LEAST(discount_value, items_subtotal + toppings_subtotal)));
SQL);
    }
};
