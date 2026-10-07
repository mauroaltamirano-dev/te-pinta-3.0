<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inventory_items', function (Blueprint $table): void {
            $table->decimal('reference_mass_grams_per_unit', 20, 6)->nullable();
        });
        DB::statement("ALTER TABLE inventory_items ADD CONSTRAINT inventory_items_reference_mass_valid CHECK (reference_mass_grams_per_unit IS NULL OR (unit_family = 'count' AND reference_mass_grams_per_unit > 0))");
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE inventory_items DROP CONSTRAINT inventory_items_reference_mass_valid');
        Schema::table('inventory_items', fn (Blueprint $table) => $table->dropColumn('reference_mass_grams_per_unit'));
    }
};
