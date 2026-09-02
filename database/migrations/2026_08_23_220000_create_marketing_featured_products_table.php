<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('marketing_featured_products', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->string('headline', 80)->nullable();
            $table->string('short_copy', 240)->nullable();
            $table->timestampTz('starts_at');
            $table->timestampTz('ends_at')->nullable();
            $table->boolean('is_enabled')->default(true);
            $table->timestamps();

            $table->index('product_id');
            $table->index(['is_enabled', 'starts_at']);
        });

        DB::statement(<<<'SQL'
            ALTER TABLE marketing_featured_products
            ADD CONSTRAINT marketing_featured_products_window_valid
            CHECK (ends_at IS NULL OR ends_at > starts_at)
            SQL);

        DB::statement(<<<'SQL'
            ALTER TABLE marketing_featured_products
            ADD CONSTRAINT marketing_featured_products_enabled_window_exclusion
            EXCLUDE USING gist (
                tstzrange(starts_at, COALESCE(ends_at, 'infinity'::timestamptz), '[)') WITH &&
            ) WHERE (is_enabled)
            SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('marketing_featured_products');
    }
};
