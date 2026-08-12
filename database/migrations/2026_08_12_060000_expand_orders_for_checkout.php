<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE orders DROP CONSTRAINT IF EXISTS orders_delivery_slot_valid');
            DB::statement('ALTER TABLE orders DROP CONSTRAINT IF EXISTS orders_status_valid');
            DB::statement('ALTER TABLE order_items DROP CONSTRAINT IF EXISTS order_items_pricing_mode_valid');
            DB::statement('ALTER TABLE order_items DROP CONSTRAINT IF EXISTS order_items_values_positive');
        }

        Schema::table('orders', function (Blueprint $table): void {
            $table->renameColumn('delivery_slot', 'delivery_period');
        });

        Schema::table('orders', function (Blueprint $table): void {
            $table->string('source', 24)->default('admin')->after('customer_id')->index();
            $table->string('customer_name_snapshot')->default('Historical customer')->after('source');
            $table->string('customer_phone_snapshot', 40)->nullable()->after('customer_name_snapshot');
            $table->string('condition', 12)->default('raw')->after('payment_status');
            $table->string('delivery_type', 12)->default('pickup')->after('condition');
            $table->text('delivery_address_snapshot')->nullable()->after('delivery_time');
            $table->text('notes')->nullable()->after('delivery_address_snapshot');
            $table->decimal('items_subtotal', 12, 2)->default(0)->after('notes');
            $table->decimal('toppings_subtotal', 12, 2)->default(0)->after('items_subtotal');
            $table->decimal('shipping_fee_snapshot', 12, 2)->default(0)->after('toppings_subtotal');
            $table->decimal('combined_dozen_savings', 12, 2)->default(0)->after('shipping_fee_snapshot');
            $table->char('public_checkout_idempotency_hash', 64)->nullable()->unique();
            $table->char('public_checkout_fingerprint', 64)->nullable();
        });

        DB::table('orders')->orderBy('id')->eachById(function (object $order): void {
            $customer = DB::table('customers')->where('id', $order->customer_id)->first(['name', 'phone']);

            DB::table('orders')->where('id', $order->id)->update([
                'customer_name_snapshot' => $customer->name,
                'customer_phone_snapshot' => $customer->phone,
                'items_subtotal' => $order->total,
            ]);
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE orders ADD CONSTRAINT orders_status_valid CHECK (status IN ('pending_confirmation', 'waiting', 'prepared', 'delivered', 'cancelled'))");
            DB::statement("ALTER TABLE orders ADD CONSTRAINT orders_source_valid CHECK (source IN ('admin', 'public_checkout'))");
            DB::statement("ALTER TABLE orders ADD CONSTRAINT orders_condition_valid CHECK (condition IN ('raw', 'cooked'))");
            DB::statement("ALTER TABLE orders ADD CONSTRAINT orders_delivery_type_valid CHECK (delivery_type IN ('delivery', 'pickup'))");
            DB::statement("ALTER TABLE orders ADD CONSTRAINT orders_delivery_period_valid CHECK (delivery_period IN ('midday', 'afternoon', 'night'))");
            DB::statement('ALTER TABLE orders ADD CONSTRAINT orders_public_checkout_idempotency_pair_valid CHECK ((public_checkout_idempotency_hash IS NULL) = (public_checkout_fingerprint IS NULL))');
            DB::statement('ALTER TABLE orders ADD CONSTRAINT orders_money_nonnegative CHECK (items_subtotal >= 0 AND toppings_subtotal >= 0 AND shipping_fee_snapshot >= 0 AND combined_dozen_savings >= 0 AND total >= 0)');
            DB::statement('ALTER TABLE orders ADD CONSTRAINT orders_total_matches_breakdown CHECK (total = items_subtotal + toppings_subtotal + shipping_fee_snapshot)');
            DB::statement("ALTER TABLE orders ADD CONSTRAINT orders_delivery_details_valid CHECK ((delivery_type = 'pickup' AND shipping_fee_snapshot = 0) OR (delivery_type = 'delivery' AND delivery_address_snapshot IS NOT NULL AND BTRIM(delivery_address_snapshot) <> ''))");
        }

        Schema::table('order_items', function (Blueprint $table): void {
            $table->unsignedInteger('units_count')->default(1)->after('quantity');
            $table->decimal('regular_price_total', 12, 2)->nullable()->after('line_total');
            $table->decimal('savings', 12, 2)->default(0)->after('regular_price_total');
            $table->foreignId('product_id')->nullable()->change();
        });

        DB::table('order_items')->update([
            'units_count' => DB::raw('quantity'),
            'regular_price_total' => DB::raw('line_total'),
        ]);

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE order_items ALTER COLUMN regular_price_total SET NOT NULL');
            DB::statement("ALTER TABLE order_items ADD CONSTRAINT order_items_pricing_mode_valid CHECK (pricing_mode IN ('unit', 'half_dozen', 'dozen', 'combined_dozen'))");
            DB::statement("ALTER TABLE order_items ADD CONSTRAINT order_items_product_shape_valid CHECK ((pricing_mode = 'combined_dozen' AND product_id IS NULL) OR (pricing_mode <> 'combined_dozen' AND product_id IS NOT NULL))");
            DB::statement('ALTER TABLE order_items ADD CONSTRAINT order_items_values_valid CHECK (quantity > 0 AND quantity <= 999 AND units_count > 0 AND units_count <= 9999 AND unit_price >= 0 AND line_total >= 0 AND regular_price_total >= line_total AND savings >= 0 AND savings = regular_price_total - line_total)');
        }

        Schema::create('order_item_compositions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('order_item_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->string('product_name');
            $table->unsignedInteger('quantity');
            $table->index(['order_item_id', 'product_id']);
        });

        Schema::create('order_toppings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('order_addon_id')->nullable()->constrained('order_addons')->restrictOnDelete();
            $table->string('name');
            $table->unsignedInteger('quantity');
            $table->decimal('unit_price', 12, 2);
            $table->decimal('subtotal', 12, 2);
            $table->index(['order_id', 'order_addon_id']);
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE order_item_compositions ADD CONSTRAINT order_item_compositions_quantity_valid CHECK (quantity > 0 AND quantity <= 9999)');
            DB::statement('ALTER TABLE order_toppings ADD CONSTRAINT order_toppings_values_valid CHECK (quantity > 0 AND quantity <= 999 AND unit_price >= 0 AND subtotal = quantity * unit_price)');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('order_toppings');
        Schema::dropIfExists('order_item_compositions');

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE order_items DROP CONSTRAINT IF EXISTS order_items_values_valid');
            DB::statement('ALTER TABLE order_items DROP CONSTRAINT IF EXISTS order_items_product_shape_valid');
            DB::statement('ALTER TABLE order_items DROP CONSTRAINT IF EXISTS order_items_pricing_mode_valid');
        }

        Schema::table('order_items', function (Blueprint $table): void {
            $table->dropColumn(['units_count', 'regular_price_total', 'savings']);
            $table->foreignId('product_id')->nullable(false)->change();
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE order_items ADD CONSTRAINT order_items_pricing_mode_valid CHECK (pricing_mode IN ('unit', 'half_dozen', 'dozen'))");
            DB::statement('ALTER TABLE order_items ADD CONSTRAINT order_items_values_positive CHECK (quantity > 0 AND unit_price >= 0 AND line_total >= 0)');
            DB::statement('ALTER TABLE orders DROP CONSTRAINT IF EXISTS orders_delivery_details_valid');
            DB::statement('ALTER TABLE orders DROP CONSTRAINT IF EXISTS orders_total_matches_breakdown');
            DB::statement('ALTER TABLE orders DROP CONSTRAINT IF EXISTS orders_money_nonnegative');
            DB::statement('ALTER TABLE orders DROP CONSTRAINT IF EXISTS orders_delivery_period_valid');
            DB::statement('ALTER TABLE orders DROP CONSTRAINT IF EXISTS orders_delivery_type_valid');
            DB::statement('ALTER TABLE orders DROP CONSTRAINT IF EXISTS orders_condition_valid');
            DB::statement('ALTER TABLE orders DROP CONSTRAINT IF EXISTS orders_source_valid');
            DB::statement('ALTER TABLE orders DROP CONSTRAINT IF EXISTS orders_status_valid');
        }

        Schema::table('orders', function (Blueprint $table): void {
            $table->dropUnique(['public_checkout_idempotency_hash']);
            $table->dropColumn([
                'source',
                'customer_name_snapshot',
                'customer_phone_snapshot',
                'condition',
                'delivery_type',
                'delivery_address_snapshot',
                'notes',
                'items_subtotal',
                'toppings_subtotal',
                'shipping_fee_snapshot',
                'combined_dozen_savings',
                'public_checkout_idempotency_hash',
                'public_checkout_fingerprint',
            ]);
            $table->renameColumn('delivery_period', 'delivery_slot');
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE orders ADD CONSTRAINT orders_status_valid CHECK (status IN ('waiting', 'prepared', 'delivered'))");
            DB::statement("ALTER TABLE orders ADD CONSTRAINT orders_delivery_slot_valid CHECK (delivery_slot IN ('midday', 'afternoon', 'night'))");
        }
    }
};
