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
            $table->string('payment_method', 24)->nullable()->after('payment_status');
            $table->string('discount_type', 24)->nullable()->after('combined_dozen_savings');
            $table->unsignedBigInteger('discount_value')->nullable()->after('discount_type');
            $table->decimal('discount_amount', 12, 2)->default(0)->after('discount_value');
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE orders DROP CONSTRAINT IF EXISTS orders_total_matches_breakdown');
            DB::statement('ALTER TABLE orders DROP CONSTRAINT IF EXISTS orders_money_nonnegative');
            DB::statement("ALTER TABLE orders ADD CONSTRAINT orders_payment_method_valid CHECK (payment_method IS NULL OR payment_method IN ('cash', 'bank_transfer', 'debit_card'))");
            // Existing paid orders have no trustworthy method to backfill. NOT VALID preserves them
            // while still enforcing the rule for every new or subsequently updated row.
            DB::statement("ALTER TABLE orders ADD CONSTRAINT orders_payment_method_matches_status CHECK ((payment_status = 'unpaid' AND payment_method IS NULL) OR (payment_status = 'paid' AND payment_method IS NOT NULL)) NOT VALID");
            DB::statement("ALTER TABLE orders ADD CONSTRAINT orders_discount_valid CHECK (
                (discount_type IS NULL AND discount_value IS NULL AND discount_amount = 0)
                OR (discount_type = 'percentage' AND discount_value BETWEEN 0 AND 100
                    AND discount_amount = LEAST(ROUND((items_subtotal + toppings_subtotal) * discount_value / 100.0), items_subtotal + toppings_subtotal))
                OR (discount_type = 'fixed_amount' AND discount_value >= 0
                    AND discount_amount = LEAST(discount_value, items_subtotal + toppings_subtotal))
            )");
            DB::statement('ALTER TABLE orders ADD CONSTRAINT orders_money_nonnegative CHECK (items_subtotal >= 0 AND toppings_subtotal >= 0 AND shipping_fee_snapshot >= 0 AND combined_dozen_savings >= 0 AND discount_amount >= 0 AND total >= 0)');
            DB::statement('ALTER TABLE orders ADD CONSTRAINT orders_total_matches_breakdown CHECK (total = items_subtotal + toppings_subtotal + shipping_fee_snapshot - discount_amount)');
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE orders DROP CONSTRAINT IF EXISTS orders_total_matches_breakdown');
            DB::statement('ALTER TABLE orders DROP CONSTRAINT IF EXISTS orders_money_nonnegative');
            DB::statement('ALTER TABLE orders DROP CONSTRAINT IF EXISTS orders_discount_valid');
            DB::statement('ALTER TABLE orders DROP CONSTRAINT IF EXISTS orders_payment_method_matches_status');
            DB::statement('ALTER TABLE orders DROP CONSTRAINT IF EXISTS orders_payment_method_valid');
            DB::table('orders')->update(['total' => DB::raw('items_subtotal + toppings_subtotal + shipping_fee_snapshot')]);
            DB::statement('ALTER TABLE orders ADD CONSTRAINT orders_money_nonnegative CHECK (items_subtotal >= 0 AND toppings_subtotal >= 0 AND shipping_fee_snapshot >= 0 AND combined_dozen_savings >= 0 AND total >= 0)');
            DB::statement('ALTER TABLE orders ADD CONSTRAINT orders_total_matches_breakdown CHECK (total = items_subtotal + toppings_subtotal + shipping_fee_snapshot)');
        }

        Schema::table('orders', function (Blueprint $table): void {
            $table->dropColumn(['payment_method', 'discount_type', 'discount_value', 'discount_amount']);
        });
    }
};
