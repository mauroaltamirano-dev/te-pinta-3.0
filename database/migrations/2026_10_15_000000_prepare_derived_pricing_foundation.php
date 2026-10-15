<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const ORDER_FIELDS = ['product_exact_numerator_12', 'cooked_product_exact_numerator_12', 'raw_discount_exact_numerator_12', 'discount_exact_numerator_1200', 'net_pre_round_exact_numerator_1200', 'rounding_adjustment_numerator_1200'];

    public function up(): void
    {
        // New configuration only. Never rewrite Product H/D/U or historical quotes.
        DB::table('order_settings')->insertOrIgnore(['key' => 'combined_half_dozen_surcharge', 'integer_value' => 1500, 'string_value' => null, 'created_at' => now(), 'updated_at' => now()]);
        Schema::table('orders', function (Blueprint $table): void {
            $table->unsignedSmallInteger('pricing_snapshot_version')->default(1);
            foreach (self::ORDER_FIELDS as $field) {
                $table->bigInteger($field)->nullable();
            }
        });
        Schema::table('order_items', function (Blueprint $table): void {
            $table->unsignedSmallInteger('pricing_snapshot_version')->default(1);
            foreach (['applied_exact_numerator_12', 'cooked_exact_numerator_12', 'raw_discount_exact_numerator_12', 'surcharge_exact_numerator_12'] as $field) {
                $table->bigInteger($field)->nullable();
            }
        });
        DB::statement(<<<'SQL'
ALTER TABLE orders ADD CONSTRAINT orders_reserved_exact_snapshot CHECK (
 (pricing_snapshot_version=1 AND product_exact_numerator_12 IS NULL AND cooked_product_exact_numerator_12 IS NULL
 AND raw_discount_exact_numerator_12 IS NULL AND discount_exact_numerator_1200 IS NULL
 AND net_pre_round_exact_numerator_1200 IS NULL AND rounding_adjustment_numerator_1200 IS NULL)
 OR (pricing_snapshot_version=2 AND product_exact_numerator_12 IS NOT NULL AND product_exact_numerator_12>=0
 AND cooked_product_exact_numerator_12 IS NOT NULL AND raw_discount_exact_numerator_12 IS NOT NULL AND raw_discount_exact_numerator_12>=0
 AND cooked_product_exact_numerator_12=product_exact_numerator_12+raw_discount_exact_numerator_12
 AND discount_exact_numerator_1200 IS NOT NULL AND discount_exact_numerator_1200>=0
 AND net_pre_round_exact_numerator_1200 IS NOT NULL AND net_pre_round_exact_numerator_1200>=0
 AND rounding_adjustment_numerator_1200 IS NOT NULL))
SQL);
        DB::statement(<<<'SQL'
ALTER TABLE order_items ADD CONSTRAINT order_items_reserved_exact_snapshot CHECK (
 (pricing_snapshot_version=1 AND applied_exact_numerator_12 IS NULL AND cooked_exact_numerator_12 IS NULL AND raw_discount_exact_numerator_12 IS NULL AND surcharge_exact_numerator_12 IS NULL)
 OR (pricing_snapshot_version=2 AND applied_exact_numerator_12 IS NOT NULL AND applied_exact_numerator_12>=0
 AND cooked_exact_numerator_12 IS NOT NULL AND raw_discount_exact_numerator_12 IS NOT NULL AND raw_discount_exact_numerator_12>=0
 AND cooked_exact_numerator_12=applied_exact_numerator_12+raw_discount_exact_numerator_12
 AND surcharge_exact_numerator_12 IS NOT NULL AND surcharge_exact_numerator_12>=0 AND surcharge_exact_numerator_12<=applied_exact_numerator_12))
SQL);
        // Keep AOB-5's existing guard intact; extend protection to every reserved order field.
        // Its line/composition guards already reject ALL writes after consumption, including new columns.
        DB::unprepared(<<<'SQL'
CREATE OR REPLACE FUNCTION inventory_order_exact_economic_guard() RETURNS trigger LANGUAGE plpgsql AS $$
BEGIN
 IF EXISTS(SELECT 1 FROM order_inventory_consumptions WHERE order_id=OLD.id)
 AND (NEW.pricing_snapshot_version,NEW.product_exact_numerator_12,NEW.cooked_product_exact_numerator_12,NEW.raw_discount_exact_numerator_12,NEW.discount_exact_numerator_1200,NEW.net_pre_round_exact_numerator_1200,NEW.rounding_adjustment_numerator_1200)
 IS DISTINCT FROM (OLD.pricing_snapshot_version,OLD.product_exact_numerator_12,OLD.cooked_product_exact_numerator_12,OLD.raw_discount_exact_numerator_12,OLD.discount_exact_numerator_1200,OLD.net_pre_round_exact_numerator_1200,OLD.rounding_adjustment_numerator_1200)
 THEN RAISE EXCEPTION 'Fulfilled exact economics cannot be rewritten' USING ERRCODE='55000'; END IF;
 RETURN NEW;
END; $$;
CREATE TRIGGER inventory_order_exact_economic_guard BEFORE UPDATE ON orders FOR EACH ROW EXECUTE FUNCTION inventory_order_exact_economic_guard();
SQL);
    }

    public function down(): void
    {
        if (DB::table('orders')->where('pricing_snapshot_version', '<>', 1)->exists() || DB::table('order_items')->where('pricing_snapshot_version', '<>', 1)->exists()) {
            throw new RuntimeException('Cannot remove exact snapshots while versioned commercial history exists.');
        }
        DB::unprepared('DROP TRIGGER inventory_order_exact_economic_guard ON orders; DROP FUNCTION inventory_order_exact_economic_guard(); ALTER TABLE orders DROP CONSTRAINT orders_reserved_exact_snapshot; ALTER TABLE order_items DROP CONSTRAINT order_items_reserved_exact_snapshot;');
        Schema::table('orders', fn (Blueprint $table) => $table->dropColumn(['pricing_snapshot_version', ...self::ORDER_FIELDS]));
        Schema::table('order_items', fn (Blueprint $table) => $table->dropColumn(['pricing_snapshot_version', 'applied_exact_numerator_12', 'cooked_exact_numerator_12', 'raw_discount_exact_numerator_12', 'surcharge_exact_numerator_12']));
        // Keep operator-edited configuration; a re-apply never overwrites it.
    }
};
