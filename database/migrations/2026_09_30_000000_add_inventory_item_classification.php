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
            $table->string('kind', 24)->nullable();
            $table->boolean('recipe_input_enabled')->default(false);
            $table->boolean('production_output_enabled')->default(false);
            $table->boolean('order_consumption_enabled')->default(false);
            $table->index(['is_active', 'tracks_stock', 'recipe_input_enabled'], 'inventory_items_recipe_input_index');
            $table->index(['is_active', 'tracks_stock', 'production_output_enabled'], 'inventory_items_production_output_index');
        });

        DB::statement("ALTER TABLE inventory_items ADD CONSTRAINT inventory_items_kind_valid CHECK (kind IS NULL OR kind IN ('raw_material', 'semi_finished', 'finished', 'packaging', 'consumable', 'other'))");

    }

    public function down(): void
    {
        DB::statement('ALTER TABLE inventory_items DROP CONSTRAINT inventory_items_kind_valid');
        Schema::table('inventory_items', function (Blueprint $table): void {
            $table->dropIndex('inventory_items_recipe_input_index');
            $table->dropIndex('inventory_items_production_output_index');
            $table->dropColumn(['kind', 'recipe_input_enabled', 'production_output_enabled', 'order_consumption_enabled']);
        });
    }
};
