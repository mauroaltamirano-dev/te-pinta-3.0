<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * pg_restore runs with an empty search_path, so the dining_areas check failed while restoring data:
 * restaurant_obstacles_valid calls restaurant_geometry_valid unqualified. Pinning the search_path on
 * the floor functions makes a plain pg_restore work. Function configuration only; no row changes.
 */
return new class extends Migration
{
    private const FUNCTIONS = [
        'public.restaurant_geometry_valid(numeric, numeric, numeric, numeric, integer, integer, integer, text)',
        'public.restaurant_obstacles_valid(jsonb, integer, integer)',
        'public.restaurant_validate_final_table_bounds()',
    ];

    public function up(): void
    {
        foreach (self::FUNCTIONS as $function) {
            DB::statement("ALTER FUNCTION {$function} SET search_path = public");
        }
    }

    public function down(): void
    {
        foreach (self::FUNCTIONS as $function) {
            DB::statement("ALTER FUNCTION {$function} RESET search_path");
        }
    }
};
