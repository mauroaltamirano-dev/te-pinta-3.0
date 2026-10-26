<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE orders DROP CONSTRAINT orders_context_valid');
        // A staff quick order is a same-day pickup without customer or period until it is confirmed (or cancelled as is).
        DB::statement("ALTER TABLE orders ADD CONSTRAINT orders_context_valid CHECK (
            (delivery_type = 'dine_in' AND source = 'pos' AND delivery_date IS NULL AND delivery_period IS NULL AND delivery_time IS NULL)
            OR (delivery_type <> 'dine_in' AND customer_id IS NOT NULL AND customer_name_snapshot IS NOT NULL AND delivery_date IS NOT NULL AND delivery_period IS NOT NULL)
            OR (delivery_type = 'pickup' AND source = 'admin' AND status IN ('pending_confirmation', 'cancelled') AND delivery_date IS NOT NULL
                AND (customer_id IS NULL) = (customer_name_snapshot IS NULL))
        )");
    }

    public function down(): void
    {
        if (DB::table('orders')->where('delivery_type', '<>', 'dine_in')
            ->where(fn ($query) => $query->whereNull('customer_id')->orWhereNull('customer_name_snapshot')->orWhereNull('delivery_period'))
            ->exists()) {
            throw new RuntimeException('Quick pending orders without customer or period exist; complete or reconcile them before rollback.');
        }

        DB::statement('ALTER TABLE orders DROP CONSTRAINT orders_context_valid');
        DB::statement("ALTER TABLE orders ADD CONSTRAINT orders_context_valid CHECK (
            (delivery_type = 'dine_in' AND source = 'pos' AND delivery_date IS NULL AND delivery_period IS NULL AND delivery_time IS NULL)
            OR (delivery_type <> 'dine_in' AND customer_id IS NOT NULL AND customer_name_snapshot IS NOT NULL AND delivery_date IS NOT NULL AND delivery_period IS NOT NULL)
        )");
    }
};
