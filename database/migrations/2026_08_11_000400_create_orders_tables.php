<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table): void {
            $table->id();
            $table->string('public_id', 32)->unique();
            $table->foreignId('customer_id')->constrained()->restrictOnDelete();
            $table->string('status', 20)->default('waiting')->index();
            $table->string('payment_status', 20)->default('unpaid')->index();
            $table->string('delivery_slot', 20)->default('night');
            $table->date('delivery_date');
            $table->time('delivery_time')->nullable();
            $table->decimal('total', 12, 2)->default(0);
            $table->timestamps();
            $table->softDeletes();
            $table->index(['delivery_date', 'delivery_slot']);
        });

        Schema::create('order_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->string('product_name');
            $table->string('pricing_mode', 20);
            $table->unsignedInteger('quantity');
            $table->decimal('unit_price', 12, 2);
            $table->decimal('line_total', 12, 2);
            $table->index(['order_id', 'product_id']);
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('DROP SEQUENCE IF EXISTS order_public_id_seq');
            DB::statement('CREATE SEQUENCE order_public_id_seq START 1');
            DB::statement("ALTER TABLE orders ALTER COLUMN public_id SET DEFAULT ('TP-' || LPAD(nextval('order_public_id_seq')::text, 6, '0'))");
            DB::statement('ALTER SEQUENCE order_public_id_seq OWNED BY orders.public_id');
            DB::statement("ALTER TABLE orders ADD CONSTRAINT orders_status_valid CHECK (status IN ('waiting', 'prepared', 'delivered'))");
            DB::statement("ALTER TABLE orders ADD CONSTRAINT orders_payment_status_valid CHECK (payment_status IN ('unpaid', 'paid'))");
            DB::statement("ALTER TABLE orders ADD CONSTRAINT orders_delivery_slot_valid CHECK (delivery_slot IN ('midday', 'afternoon', 'night'))");
            DB::statement('ALTER TABLE orders ADD CONSTRAINT orders_total_nonnegative CHECK (total >= 0)');
            DB::statement("ALTER TABLE order_items ADD CONSTRAINT order_items_pricing_mode_valid CHECK (pricing_mode IN ('unit', 'half_dozen', 'dozen'))");
            DB::statement('ALTER TABLE order_items ADD CONSTRAINT order_items_values_positive CHECK (quantity > 0 AND unit_price >= 0 AND line_total >= 0)');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('order_items');
        Schema::dropIfExists('orders');

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('DROP SEQUENCE IF EXISTS order_public_id_seq');
        }
    }
};
