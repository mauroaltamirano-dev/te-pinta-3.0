<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_settings', function (Blueprint $table): void {
            $table->id();
            $table->string('key')->unique();
            $table->unsignedInteger('integer_value')->nullable();
            $table->string('string_value')->nullable();
            $table->timestamps();
        });

        DB::statement('ALTER TABLE order_settings ADD CONSTRAINT order_settings_typed_value CHECK ((integer_value IS NOT NULL)::int + (string_value IS NOT NULL)::int = 1)');

        Schema::create('order_addons', function (Blueprint $table): void {
            $table->id();
            $table->string('slug')->unique();
            $table->string('name');
            $table->unsignedInteger('price');
            $table->boolean('is_available')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
            $table->softDeletes();
            $table->index(['is_available', 'sort_order']);
        });

        $now = now();
        DB::table('order_settings')->insert([
            ['key' => 'combined_dozen_raw_price', 'integer_value' => 16000, 'string_value' => null, 'created_at' => $now, 'updated_at' => $now],
            ['key' => 'combined_dozen_cooked_price', 'integer_value' => 17500, 'string_value' => null, 'created_at' => $now, 'updated_at' => $now],
            ['key' => 'delivery_fee', 'integer_value' => 0, 'string_value' => null, 'created_at' => $now, 'updated_at' => $now],
            ['key' => 'whatsapp_order_phone', 'integer_value' => null, 'string_value' => '', 'created_at' => $now, 'updated_at' => $now],
        ]);
        DB::table('order_addons')->insert([
            ['slug' => 'lactonesa', 'name' => 'Lactonesa', 'price' => 500, 'is_available' => true, 'sort_order' => 1, 'created_at' => $now, 'updated_at' => $now],
            ['slug' => 'yasgua', 'name' => 'Yasgua', 'price' => 200, 'is_available' => true, 'sort_order' => 2, 'created_at' => $now, 'updated_at' => $now],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('order_addons');
        Schema::dropIfExists('order_settings');
    }
};
