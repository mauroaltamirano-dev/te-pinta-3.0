<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->unsignedBigInteger('edit_version')->default(1);
        });

        Schema::create('order_revisions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('admin_id')->constrained('users')->restrictOnDelete();
            $table->string('order_status', 20);
            $table->decimal('total_before', 12, 2);
            $table->decimal('total_after', 12, 2);
            $table->json('changed_fields');
            $table->string('reason', 300)->nullable();
            $table->timestamp('created_at');
            $table->index(['order_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_revisions');
        Schema::table('orders', fn (Blueprint $table) => $table->dropColumn('edit_version'));
    }
};
