<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('restaurants', function (Blueprint $table): void {
            $table->string('accent_color', 7)->default('#b84f3f');
        });

        DB::statement("ALTER TABLE restaurants ADD CONSTRAINT restaurants_accent_color_hex CHECK (accent_color ~ '^#[0-9a-f]{6}$')");
    }

    public function down(): void
    {
        Schema::table('restaurants', function (Blueprint $table): void {
            $table->dropColumn('accent_color');
        });
    }
};
