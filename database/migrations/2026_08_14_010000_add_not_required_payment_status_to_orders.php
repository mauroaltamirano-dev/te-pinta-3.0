<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE orders DROP CONSTRAINT orders_payment_method_matches_status');
        DB::statement('ALTER TABLE orders DROP CONSTRAINT orders_payment_status_valid');
        DB::statement("UPDATE orders SET payment_status = 'not_required', payment_method = NULL WHERE total = 0");
        DB::statement("ALTER TABLE orders ADD CONSTRAINT orders_payment_status_valid CHECK (payment_status IN ('unpaid', 'paid', 'not_required'))");
        // Historical paid orders predate payment methods. Keep those rows readable while
        // enforcing the complete invariant for every new or subsequently updated order.
        DB::statement("ALTER TABLE orders ADD CONSTRAINT orders_payment_matches_total CHECK ((payment_status = 'not_required' AND total = 0 AND payment_method IS NULL) OR (payment_status = 'unpaid' AND payment_method IS NULL) OR (payment_status = 'paid' AND payment_method IS NOT NULL)) NOT VALID");
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE orders DROP CONSTRAINT orders_payment_matches_total');
        DB::statement("UPDATE orders SET payment_status = 'unpaid', payment_method = NULL WHERE payment_status = 'not_required'");
        DB::statement("ALTER TABLE orders ADD CONSTRAINT orders_payment_status_valid CHECK (payment_status IN ('unpaid', 'paid'))");
        DB::statement("ALTER TABLE orders ADD CONSTRAINT orders_payment_method_matches_status CHECK ((payment_status = 'unpaid' AND payment_method IS NULL) OR (payment_status = 'paid' AND payment_method IS NOT NULL))");
    }
};
