<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Change structural contracts only. Never rewrite historical snapshots or links.
        foreach (['customer_id', 'customer_name_snapshot', 'delivery_date', 'delivery_period'] as $column) {
            DB::statement("ALTER TABLE orders ALTER COLUMN {$column} DROP NOT NULL");
        }
        DB::statement('ALTER TABLE orders DROP CONSTRAINT orders_source_valid');
        DB::statement('ALTER TABLE orders DROP CONSTRAINT orders_delivery_type_valid');
        DB::statement('ALTER TABLE orders DROP CONSTRAINT orders_delivery_details_valid');
        DB::statement("ALTER TABLE orders ADD CONSTRAINT orders_source_valid CHECK (source IN ('admin', 'public_checkout', 'pos'))");
        DB::statement("ALTER TABLE orders ADD CONSTRAINT orders_delivery_type_valid CHECK (delivery_type IN ('delivery', 'pickup', 'dine_in'))");
        DB::statement("ALTER TABLE orders ADD CONSTRAINT orders_delivery_details_valid CHECK (
            (delivery_type = 'pickup' AND shipping_fee_snapshot = 0)
            OR (delivery_type = 'delivery' AND delivery_address_snapshot IS NOT NULL AND BTRIM(delivery_address_snapshot) <> '')
            OR (delivery_type = 'dine_in' AND shipping_fee_snapshot = 0 AND delivery_address_snapshot IS NULL)
        )");
        DB::statement("ALTER TABLE orders ADD CONSTRAINT orders_context_valid CHECK (
            (delivery_type = 'dine_in' AND source = 'pos' AND delivery_date IS NULL AND delivery_period IS NULL AND delivery_time IS NULL)
            OR (delivery_type <> 'dine_in' AND customer_id IS NOT NULL AND customer_name_snapshot IS NOT NULL AND delivery_date IS NOT NULL AND delivery_period IS NOT NULL)
        )");
    }

    public function down(): void
    {
        if (DB::table('orders')->where('source', 'pos')->orWhere('delivery_type', 'dine_in')
            ->orWhereNull('customer_id')->orWhereNull('customer_name_snapshot')
            ->orWhereNull('delivery_date')->orWhereNull('delivery_period')->exists()) {
            throw new RuntimeException('POS order context is in use; reconcile data explicitly before rollback.');
        }
        DB::statement('ALTER TABLE orders DROP CONSTRAINT orders_context_valid');
        DB::statement('ALTER TABLE orders DROP CONSTRAINT orders_source_valid');
        DB::statement('ALTER TABLE orders DROP CONSTRAINT orders_delivery_type_valid');
        DB::statement('ALTER TABLE orders DROP CONSTRAINT orders_delivery_details_valid');
        DB::statement("ALTER TABLE orders ADD CONSTRAINT orders_source_valid CHECK (source IN ('admin', 'public_checkout'))");
        DB::statement("ALTER TABLE orders ADD CONSTRAINT orders_delivery_type_valid CHECK (delivery_type IN ('delivery', 'pickup'))");
        DB::statement("ALTER TABLE orders ADD CONSTRAINT orders_delivery_details_valid CHECK ((delivery_type = 'pickup' AND shipping_fee_snapshot = 0) OR (delivery_type = 'delivery' AND delivery_address_snapshot IS NOT NULL AND BTRIM(delivery_address_snapshot) <> ''))");
        foreach (['customer_id', 'customer_name_snapshot', 'delivery_date', 'delivery_period'] as $column) {
            DB::statement("ALTER TABLE orders ALTER COLUMN {$column} SET NOT NULL");
        }
    }
};
