<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Combined dozen by percentage: a mixed dozen costs the sum of its twelve unit
 * prices minus this percentage (docs/ORDERS_AND_PRICING.md). Seeded at 20 for
 * every restaurant; editable in Configuración.
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
             select id, 'combined_dozen_discount_percent', 20, null, now(), now() from restaurants
             on conflict (restaurant_id, key) do nothing",
        );
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::table('order_settings')->where('key', 'combined_dozen_discount_percent')->delete();
    }
};
