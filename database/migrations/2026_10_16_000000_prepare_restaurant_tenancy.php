<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Makes the schema tenant-ready: every business row belongs to a restaurant.
 *
 * `restaurant_id` defaults to the PostgreSQL session setting
 * `app.current_restaurant_id` (set by App\Core\Tenancy\RestaurantContext), so
 * existing write paths keep working and a write without context fails.
 * Existing rows belong to restaurant 1. Query scoping (row-level security)
 * is a separate step.
 */
return new class extends Migration
{
    /** Every table except framework tables, users (membership-based) and system units. */
    private const BUSINESS_TABLES = [
        'categories', 'customers', 'dining_areas', 'dining_tables', 'financial_transactions',
        'instagram_automation_settings', 'instagram_story_assets', 'instagram_story_publications',
        'instagram_story_schedules', 'inventory_adjustments', 'inventory_categories', 'inventory_cost_layers',
        'inventory_costing_policies', 'inventory_costing_policy_packaging_components',
        'inventory_costing_policy_presentations', 'inventory_items', 'inventory_product_consumption_components',
        'inventory_product_consumption_definitions', 'inventory_product_consumption_versions',
        'inventory_product_costing_policies', 'inventory_production_batches', 'inventory_production_cost_allocations',
        'inventory_production_reversals', 'inventory_purchase_presentations', 'inventory_recipe_ingredients',
        'inventory_recipe_versions', 'inventory_recipes', 'inventory_stock_movements', 'inventory_transactions',
        'ledger_entries', 'management_attachments', 'management_distribution_allocations',
        'management_distribution_rule_allocations', 'management_distribution_rule_versions',
        'management_distribution_rules', 'management_distributions', 'management_order_payments',
        'management_outflow_categories', 'management_outflows', 'management_transfers',
        'marketing_featured_products', 'order_addons', 'order_inventory_consumption_components',
        'order_inventory_consumption_reversals', 'order_inventory_consumptions', 'order_item_compositions',
        'order_items', 'order_revisions', 'order_settings', 'order_toppings', 'orders', 'product_tag',
        'products', 'purchase_lines', 'purchase_reversals', 'purchases', 'raw_price_discounts', 'tags', 'wallets',
    ];

    /** Unique rules that must hold per restaurant: name => [table, new definition]. */
    private const SCOPED_UNIQUE_CONSTRAINTS = [
        'categories_slug_unique' => ['categories', 'unique (restaurant_id, slug)'],
        'products_slug_unique' => ['products', 'unique (restaurant_id, slug)'],
        'tags_slug_unique' => ['tags', 'unique (restaurant_id, slug)'],
        'order_addons_slug_unique' => ['order_addons', 'unique (restaurant_id, slug)'],
        'order_settings_key_unique' => ['order_settings', 'unique (restaurant_id, key)'],
        'cooking_surcharges_pricing_mode_unique' => ['raw_price_discounts', 'unique (restaurant_id, pricing_mode)'],
        'management_outflow_categories_type_code_unique' => ['management_outflow_categories', 'unique (restaurant_id, type, code)'],
    ];

    private const SCOPED_UNIQUE_INDEXES = [
        'customers_active_phone_normalized_unique' => 'customers (restaurant_id, phone_normalized) where phone_normalized is not null and deleted_at is null',
        'inventory_categories_name_unique' => 'inventory_categories (restaurant_id, lower(name::text))',
        'inventory_items_name_unique' => 'inventory_items (restaurant_id, lower(name::text))',
        'inventory_recipe_name_unique' => 'inventory_recipes (restaurant_id, lower(btrim(name::text)))',
        'costing_policy_name_unique' => 'inventory_costing_policies (restaurant_id, lower(name::text))',
        'management_distribution_rules_single_active_unique' => 'management_distribution_rules (restaurant_id) where active',
        'wallets_single_order_payment_default_unique' => 'wallets (restaurant_id) where is_order_payment_default',
    ];

    public function up(): void
    {
        Schema::create('restaurants', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->timestamps();
        });

        DB::table('restaurants')->insert([
            'id' => 1,
            'name' => 'Te Pinta',
            'slug' => 'te-pinta',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::statement("select setval(pg_get_serial_sequence('restaurants', 'id'), 1)");

        // Existing rows take the value of the setting while the column is added.
        $previous = DB::selectOne("select current_setting('app.current_restaurant_id', true) as value")->value;
        DB::statement("select set_config('app.current_restaurant_id', '1', true)");

        foreach (self::BUSINESS_TABLES as $table) {
            DB::statement(<<<SQL
                alter table {$table}
                    add column restaurant_id bigint not null
                    default nullif(current_setting('app.current_restaurant_id', true), '')::bigint
                    constraint {$table}_restaurant_id_foreign references restaurants (id) on delete restrict
                SQL);
            DB::statement("create index {$table}_restaurant_id_index on {$table} (restaurant_id)");
        }

        DB::statement("select set_config('app.current_restaurant_id', ?, true)", [(string) $previous]);

        foreach (self::SCOPED_UNIQUE_CONSTRAINTS as $name => [$table, $definition]) {
            DB::statement("alter table {$table} drop constraint {$name}");
            DB::statement("alter table {$table} add constraint {$name} {$definition}");
        }

        foreach (self::SCOPED_UNIQUE_INDEXES as $name => $definition) {
            DB::statement("drop index {$name}");
            DB::statement("create unique index {$name} on {$definition}");
        }

        DB::statement('create extension if not exists btree_gist');
        DB::statement('alter table marketing_featured_products drop constraint marketing_featured_products_enabled_window_exclusion');
        DB::statement(<<<'SQL'
            alter table marketing_featured_products
                add constraint marketing_featured_products_enabled_window_exclusion
                exclude using gist (
                    restaurant_id with =,
                    tstzrange(starts_at, coalesce(ends_at, 'infinity'::timestamptz), '[)') with &&
                ) where (is_enabled)
            SQL);
    }

    public function down(): void
    {
        DB::statement('alter table marketing_featured_products drop constraint marketing_featured_products_enabled_window_exclusion');
        DB::statement(<<<'SQL'
            alter table marketing_featured_products
                add constraint marketing_featured_products_enabled_window_exclusion
                exclude using gist (
                    tstzrange(starts_at, coalesce(ends_at, 'infinity'::timestamptz), '[)') with &&
                ) where (is_enabled)
            SQL);

        foreach (self::SCOPED_UNIQUE_INDEXES as $name => $definition) {
            DB::statement("drop index {$name}");
            DB::statement('create unique index '.$name.' on '.str_replace(['(restaurant_id, ', '(restaurant_id)'], ['(', '((1))'], $definition));
        }

        foreach (self::SCOPED_UNIQUE_CONSTRAINTS as $name => [$table, $definition]) {
            DB::statement("alter table {$table} drop constraint {$name}");
            DB::statement("alter table {$table} add constraint {$name} ".str_replace('(restaurant_id, ', '(', $definition));
        }

        foreach (self::BUSINESS_TABLES as $table) {
            DB::statement("alter table {$table} drop column restaurant_id");
        }

        Schema::drop('restaurants');
    }
};
