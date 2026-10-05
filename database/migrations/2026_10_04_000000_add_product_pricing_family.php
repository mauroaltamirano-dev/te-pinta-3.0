<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            $table->string('pricing_family', 32)->nullable()->after('category_id');
        });

        $invalidLegacyProducts = DB::table('products')
            ->whereNull('unit_price')
            ->orWhereNull('half_dozen_price')
            ->orWhereNull('dozen_price')
            ->exists();

        if ($invalidLegacyProducts) {
            throw new RuntimeException('Cannot classify legacy products without all three structural price tiers. Review the catalog manually before migrating.');
        }

        DB::table('products')->whereNull('pricing_family')->update(['pricing_family' => 'empanada']);

        Schema::table('products', function (Blueprint $table): void {
            $table->string('pricing_family', 32)->default('empanada')->nullable(false)->change();
        });

        DB::statement("ALTER TABLE products ADD CONSTRAINT products_pricing_family_valid CHECK (pricing_family IN ('empanada', 'unit'))");
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE products DROP CONSTRAINT IF EXISTS products_pricing_family_valid');

        Schema::table('products', function (Blueprint $table): void {
            $table->dropColumn('pricing_family');
        });
    }
};
