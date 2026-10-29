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
            $table->string('public_eyebrow', 60)->nullable();
            $table->string('public_headline', 80)->nullable();
            $table->string('public_headline_highlight', 30)->nullable();
            $table->string('public_intro', 200)->nullable();
            $table->string('public_home_title', 60)->nullable();
            $table->jsonb('public_highlights')->default(DB::raw("'[]'::jsonb"));
            $table->string('public_footer_text', 200)->nullable();
            $table->string('public_location', 60)->nullable();
            $table->string('public_coming_soon_text', 200)->nullable();
            $table->string('public_empty_cart_text', 200)->nullable();
            $table->jsonb('public_offerings')->default(DB::raw("'[]'::jsonb"));
            $table->jsonb('public_dine_in_items')->default(DB::raw("'[]'::jsonb"));
            $table->string('public_maps_url', 255)->nullable();
        });

        DB::statement("ALTER TABLE restaurants ADD CONSTRAINT restaurants_public_highlights_array CHECK (jsonb_typeof(public_highlights) = 'array' AND jsonb_array_length(public_highlights) <= 3)");
        DB::statement("ALTER TABLE restaurants ADD CONSTRAINT restaurants_public_offerings_array CHECK (jsonb_typeof(public_offerings) = 'array' AND jsonb_array_length(public_offerings) <= 6)");
        DB::statement("ALTER TABLE restaurants ADD CONSTRAINT restaurants_public_dine_in_items_array CHECK (jsonb_typeof(public_dine_in_items) = 'array' AND jsonb_array_length(public_dine_in_items) <= 6)");

        // Te Pinta's storefront copy moves from the code to its restaurant row unchanged.
        DB::table('restaurants')->where('slug', 'te-pinta')->update([
            'public_eyebrow' => 'Te Pinta · Bell Ville',
            'public_headline' => '¿Qué te pinta hoy?',
            'public_headline_highlight' => 'pinta',
            'public_intro' => 'Empanadas caseras, pizzas y más. Pedilas para llevar, recibilas en casa o vení a comer al local.',
            'public_home_title' => 'Empanadas y pizzas',
            'public_highlights' => json_encode([
                'Comé en el local, con servicio a la mesa',
                'Retiro o envío a domicilio',
                'Confirmás tu pedido por WhatsApp',
            ], JSON_UNESCAPED_UNICODE),
            'public_footer_text' => 'Elegí del menú, armá tu pedido y confirmalo por WhatsApp.',
            'public_location' => 'Bell Ville, Córdoba',
            'public_coming_soon_text' => 'Muy pronto vas a poder pedir tus empanadas desde acá. Y se vienen novedades.',
            'public_empty_cart_text' => 'El menú te espera con nuestras empanadas. Sumá las que quieras y volvé por acá.',
            'public_offerings' => json_encode([
                'Empanadas, pizzas y más',
                'Comé en el local',
                'Retiro o envío a domicilio',
                'Confirmación por WhatsApp',
            ], JSON_UNESCAPED_UNICODE),
            'public_dine_in_items' => json_encode([
                'Servicio a la mesa',
                'Empanadas y pizzas',
                'Bebidas',
                'Postres',
            ], JSON_UNESCAPED_UNICODE),
            'public_maps_url' => 'https://maps.app.goo.gl/CoZHPYbs9VHNMH9L6',
        ]);
    }

    public function down(): void
    {
        Schema::table('restaurants', function (Blueprint $table): void {
            $table->dropColumn([
                'public_eyebrow',
                'public_headline',
                'public_headline_highlight',
                'public_intro',
                'public_home_title',
                'public_highlights',
                'public_footer_text',
                'public_location',
                'public_coming_soon_text',
                'public_empty_cart_text',
                'public_offerings',
                'public_dine_in_items',
                'public_maps_url',
            ]);
        });
    }
};
