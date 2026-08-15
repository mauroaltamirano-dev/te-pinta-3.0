<?php

namespace Tests\Feature\Orders;

use App\Modules\Customers\Models\Customer;
use App\Modules\Orders\Models\Order;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class OrderDatabaseConstraintTest extends TestCase
{
    use RefreshDatabase;

    public function test_postgresql_defines_order_snapshot_and_value_constraints(): void
    {
        $constraints = DB::table('pg_constraint')
            ->whereIn('conname', [
                'orders_status_valid',
                'orders_payment_status_valid',
                'orders_payment_method_valid',
                'orders_payment_matches_total',
                'orders_discount_valid',
                'orders_source_valid',
                'orders_condition_valid',
                'orders_delivery_type_valid',
                'orders_delivery_period_valid',
                'orders_money_nonnegative',
                'orders_total_matches_breakdown',
                'orders_cooking_breakdown_valid',
                'orders_delivery_details_valid',
                'orders_public_checkout_idempotency_pair_valid',
                'order_items_pricing_mode_valid',
                'order_items_product_shape_valid',
                'order_items_values_valid',
                'order_items_cooking_breakdown_valid',
                'order_item_compositions_quantity_valid',
                'order_toppings_values_valid',
            ])
            ->pluck('conname')
            ->all();

        $this->assertEqualsCanonicalizing([
            'orders_status_valid',
            'orders_payment_status_valid',
            'orders_payment_method_valid',
            'orders_payment_matches_total',
            'orders_discount_valid',
            'orders_source_valid',
            'orders_condition_valid',
            'orders_delivery_type_valid',
            'orders_delivery_period_valid',
            'orders_money_nonnegative',
            'orders_total_matches_breakdown',
            'orders_cooking_breakdown_valid',
            'orders_delivery_details_valid',
            'orders_public_checkout_idempotency_pair_valid',
            'order_items_pricing_mode_valid',
            'order_items_product_shape_valid',
            'order_items_values_valid',
            'order_items_cooking_breakdown_valid',
            'order_item_compositions_quantity_valid',
            'order_toppings_values_valid',
        ], $constraints);
    }

    public function test_postgresql_generates_stable_unique_public_order_ids_from_a_sequence(): void
    {
        $customer = Customer::query()->create(['name' => 'Sequence customer']);
        $attributes = [
            'customer_id' => $customer->id,
            'delivery_date' => now()->toDateString(),
        ];

        $first = Order::query()->create($attributes);
        $second = Order::query()->create($attributes);
        $first->refresh();
        $second->refresh();

        $this->assertMatchesRegularExpression('/^TP-\d{6}$/', $first->public_id);
        $this->assertMatchesRegularExpression('/^TP-\d{6}$/', $second->public_id);
        $this->assertNotSame($first->public_id, $second->public_id);
    }

    public function test_public_checkout_idempotency_hash_has_a_unique_constraint(): void
    {
        $definition = DB::table('pg_constraint')
            ->join('pg_class', 'pg_class.oid', '=', 'pg_constraint.conrelid')
            ->where('pg_class.relname', 'orders')
            ->where('pg_constraint.contype', 'u')
            ->whereRaw("pg_get_constraintdef(pg_constraint.oid) LIKE '%public_checkout_idempotency_hash%'")
            ->value('pg_constraint.conname');

        $this->assertNotNull($definition);

        $fingerprintLength = DB::table('information_schema.columns')
            ->where('table_schema', 'public')
            ->where('table_name', 'orders')
            ->where('column_name', 'public_checkout_fingerprint')
            ->value('character_maximum_length');

        $this->assertSame(64, $fingerprintLength);
    }
}
