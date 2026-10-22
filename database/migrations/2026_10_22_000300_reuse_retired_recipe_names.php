<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * A retired recipe keeps its name as history but no longer reserves it: names are
 * unique, case- and space-insensitively, among the restaurant's active recipes only.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('DROP INDEX inventory_recipe_name_unique');
        DB::statement('CREATE UNIQUE INDEX inventory_recipe_name_unique ON inventory_recipes (restaurant_id, lower(btrim(name::text))) WHERE is_active');
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }
        // Restoring the global index would fail on a reused name; never rename history silently.
        if (DB::table('inventory_recipes')->selectRaw('1')->groupByRaw('restaurant_id, lower(btrim(name::text))')->havingRaw('count(*) > 1')->exists()) {
            throw new RuntimeException('Cannot restore the global recipe name index while a retired recipe name was reused.');
        }

        DB::statement('DROP INDEX inventory_recipe_name_unique');
        DB::statement('CREATE UNIQUE INDEX inventory_recipe_name_unique ON inventory_recipes (restaurant_id, lower(btrim(name::text)))');
    }
};
