<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Raw (cook at home) empanadas by percentage: D_raw = D × (100 − p) / 100, replacing the fixed
 * raw_price_discounts amount (docs/ORDERS_AND_PRICING.md). Seeded at 5 for every restaurant;
 * editable in Configuración. The raw_price_discounts rows are kept as history and never read.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::insert(
            "insert into order_settings (restaurant_id, key, integer_value, string_value, created_at, updated_at)
             select id, 'raw_discount_percent', 5, null, now(), now() from restaurants
             on conflict (restaurant_id, key) do nothing",
        );
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::table('order_settings')->where('key', 'raw_discount_percent')->delete();
    }
};
