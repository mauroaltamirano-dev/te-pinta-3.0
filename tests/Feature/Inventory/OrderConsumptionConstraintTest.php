<?php

namespace Tests\Feature\Inventory;

use App\Modules\Identity\Models\User;
use App\Modules\Inventory\Actions\ConfigureProductInventoryConsumption;
use App\Modules\Inventory\Models\InventoryCategory;
use App\Modules\Inventory\Models\InventoryItem;
use App\Modules\Inventory\Models\InventoryUnit;
use App\Modules\Orders\Actions\CreateOrder;
use App\Modules\Orders\Actions\UpdateOrderStatus;
use App\Modules\Orders\Data\OrderCheckoutData;
use App\Modules\Orders\Enums\DeliveryType;
use App\Modules\Orders\Enums\OrderCondition;
use App\Modules\Orders\Enums\OrderSource;
use App\Modules\Orders\Enums\OrderStatus;
use App\Modules\Orders\Enums\PaymentStatus;
use App\Modules\Orders\Models\Order;
use App\Modules\Products\Models\Category;
use App\Modules\Products\Models\Product;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class OrderConsumptionConstraintTest extends TestCase
{
    use RefreshDatabase;

    public function test_database_blocks_economic_snapshots_after_even_zero_physical_fulfillment(): void
    {
        [$a,$p,$o] = $this->fixture();
        app(ConfigureProductInventoryConsumption::class)->execute($p->id, 'no_inventory', [], $a->id);
        app(UpdateOrderStatus::class)->execute($o, OrderStatus::Delivered, $a->id);
        foreach (['total', 'cooking_surcharge', 'discount_amount', 'combined_dozen_savings', 'raw_discount'] as $field) {
            $this->rejected(fn () => DB::table('orders')->where('id', $o->id)->update([$field => 1]), '55000');
        }
        $this->rejected(fn () => DB::table('order_items')->where('order_id', $o->id)->update(['units_count' => 9]), '55000');
        $this->rejected(fn () => DB::table('order_items')->where('order_id', $o->id)->delete(), '55000');
        DB::statement('SET CONSTRAINTS ALL IMMEDIATE');
    }

    public function test_database_rejects_unsealed_formula_invalid_units_and_duplicate_components(): void
    {
        [$a,$p] = $this->fixture();
        $u = InventoryUnit::query()->where('code', 'g')->sole();
        $category = InventoryCategory::query()->create(['name' => 'SQL']);
        $item = InventoryItem::query()->create(['inventory_category_id' => $category->id, 'name' => 'SQL component', 'tracks_stock' => true, 'unit_family' => 'weight', 'base_unit_id' => $u->id, 'default_input_unit_id' => $u->id, 'quantity_precision' => 6, 'is_active' => true, 'kind' => 'semi_finished', 'order_consumption_enabled' => true]);
        $d = app(ConfigureProductInventoryConsumption::class)->execute($p->id, 'consume', [['inventory_item_id' => $item->id, 'unit_id' => $u->id, 'quantity' => '55']], $a->id);
        $c = $d->currentVersion->components->sole();
        $this->rejected(fn () => DB::table('inventory_product_consumption_components')->where('id', $c->id)->update(['quantity' => 2]), '55000');
        $this->rejected(fn () => DB::table('inventory_product_consumption_versions')->where('id', $d->current_version_id)->update(['product_name_snapshot' => 'Changed']), '55000');
        $this->rejected(fn () => DB::table('inventory_product_consumption_definitions')->where('id', $d->id)->update(['mode' => 'no_inventory']), '55000');
        $this->rejected(fn () => DB::table('inventory_items')->where('id', $item->id)->update(['order_consumption_enabled' => false]), '23514');
        $this->rejected(function () use ($d, $a): void {
            DB::table('inventory_product_consumption_versions')->insert(['definition_id' => $d->id, 'version' => 2, 'product_name_snapshot' => 'New', 'created_by' => $a->id]);
            DB::statement('SET CONSTRAINTS ALL IMMEDIATE');
        }, '23514');
        DB::statement('SET CONSTRAINTS ALL IMMEDIATE');
    }

    public function test_database_requires_evidence_for_delivery_and_for_cancellation(): void
    {
        [$a,$p,$o] = $this->fixture();
        $this->rejected(function () use ($o): void {
            DB::table('orders')->where('id', $o->id)->update(['status' => 'delivered']);
            DB::statement('SET CONSTRAINTS ALL IMMEDIATE');
        }, '23514');
        app(ConfigureProductInventoryConsumption::class)->execute($p->id, 'no_inventory', [], $a->id);
        app(UpdateOrderStatus::class)->execute($o, OrderStatus::Delivered, $a->id);
        $this->rejected(function () use ($o): void {
            DB::table('orders')->where('id', $o->id)->update(['status' => 'cancelled']);
            DB::statement('SET CONSTRAINTS ALL IMMEDIATE');
        }, '23514');
        app(UpdateOrderStatus::class)->execute($o->fresh(), OrderStatus::Cancelled, $a->id);
        $this->rejected(fn () => DB::table('orders')->where('id', $o->id)->update(['status' => 'delivered']), '23514');
        $this->rejected(fn () => DB::table('order_inventory_consumptions')->where('order_id', $o->id)->update(['total_cost' => 99]), '55000');
        DB::statement('SET CONSTRAINTS ALL IMMEDIATE');
    }

    public function test_populated_migration_rollback_refuses_before_removing_history(): void
    {
        [$a,$p] = $this->fixture();
        app(ConfigureProductInventoryConsumption::class)->execute($p->id, 'no_inventory', [], $a->id);
        $migration = require database_path('migrations/2026_10_12_000000_create_order_inventory_consumption.php');
        try {
            $migration->down();
            $this->fail('Populated rollback');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('populated consumption history', $e->getMessage());
            $this->assertDatabaseCount('inventory_product_consumption_definitions', 1);
        }
    }

    public function test_database_refuses_to_capture_a_retired_formula_as_new_fulfillment(): void
    {
        [$a,$p,$o] = $this->fixture();
        $u = InventoryUnit::query()->where('code', 'g')->sole();
        $category = InventoryCategory::query()->create(['name' => 'Formula selection']);
        $item = InventoryItem::query()->create(['inventory_category_id' => $category->id, 'name' => 'Component', 'tracks_stock' => true, 'unit_family' => 'weight', 'base_unit_id' => $u->id, 'default_input_unit_id' => $u->id, 'quantity_precision' => 6, 'is_active' => true, 'kind' => 'semi_finished', 'order_consumption_enabled' => true]);
        $action = app(ConfigureProductInventoryConsumption::class);
        $old = $action->execute($p->id, 'consume', [['inventory_item_id' => $item->id, 'unit_id' => $u->id, 'quantity' => '55']], $a->id)->current_version_id;
        $action->execute($p->id, 'consume', [['inventory_item_id' => $item->id, 'unit_id' => $u->id, 'quantity' => '60']], $a->id);
        $this->rejected(function () use ($a, $p, $o, $old): void {
            DB::table('orders')->where('id', $o->id)->update(['status' => 'delivered']);
            DB::table('order_inventory_consumptions')->insert(['order_id' => $o->id, 'effective_date' => now()->toDateString(), 'total_cost' => 0, 'cost_coverage' => 'complete', 'warnings' => '[]', 'products_snapshot' => json_encode([['product_id' => $p->id, 'name' => $p->name, 'units' => 3, 'mode' => 'consume', 'version_id' => $old]], JSON_THROW_ON_ERROR), 'created_by' => $a->id]);
        }, '23514');
    }

    public function test_source_cannot_bypass_cost_capture_by_inserting_an_already_linked_transaction(): void
    {
        [$a,$p,$o] = $this->fixture();
        app(ConfigureProductInventoryConsumption::class)->execute($p->id, 'no_inventory', [], $a->id);
        $this->rejected(function () use ($a, $p, $o): void {
            DB::table('orders')->where('id', $o->id)->update(['status' => 'delivered']);
            $tx = DB::table('inventory_transactions')->insertGetId(['type' => 'sale_consumption', 'source_type' => 'order', 'source_id' => (string) $o->id, 'effective_date' => now()->toDateString(), 'created_by' => $a->id]);
            DB::table('order_inventory_consumptions')->insert(['order_id' => $o->id, 'inventory_transaction_id' => $tx, 'effective_date' => now()->toDateString(), 'total_cost' => 0, 'cost_coverage' => 'complete', 'warnings' => '[]', 'products_snapshot' => json_encode([['product_id' => $p->id, 'name' => $p->name, 'units' => 3, 'mode' => 'no_inventory', 'version_id' => null]], JSON_THROW_ON_ERROR), 'created_by' => $a->id]);
        }, '23514');
    }

    private function rejected(callable $work, string $state): void
    {
        try {
            DB::transaction($work);
            $this->fail('Expected PostgreSQL rejection');
        } catch (QueryException $e) {
            $this->assertSame($state, $e->errorInfo[0]);
        }
    }

    /** @return array{User,Product,Order} */
    private function fixture(): array
    {
        $a = User::factory()->administrator()->create();
        $c = Category::query()->create(['name' => 'Sale', 'slug' => 'sale']);
        $p = Product::query()->create(['category_id' => $c->id, 'name' => 'SQL sale', 'slug' => 'sale', 'pricing_family' => 'unit', 'unit_price' => 2500, 'half_dozen_price' => 2500, 'dozen_price' => 2500, 'is_available' => true]);
        $o = app(CreateOrder::class)->execute(new OrderCheckoutData(items: [['product_id' => $p->id, 'quantity' => 3]], addons: [], condition: OrderCondition::Cooked, deliveryType: DeliveryType::DineIn, deliveryPeriod: null, deliveryDate: null, deliveryTime: null, deliveryAddress: null, notes: null, customerId: null, customerName: null, customerPhone: null, customerPhoneNormalized: null, customerAddress: null, paymentStatus: PaymentStatus::Unpaid), OrderSource::Pos, $a->id);

        return [$a, $p, $o];
    }
}
