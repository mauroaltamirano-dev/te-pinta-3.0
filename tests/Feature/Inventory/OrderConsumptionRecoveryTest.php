<?php

namespace Tests\Feature\Inventory;

use App\Modules\Customers\Models\Customer;
use App\Modules\Identity\Models\User;
use App\Modules\Inventory\Actions\ConfigureProductInventoryConsumption;
use App\Modules\Inventory\Actions\ConsumeDeliveredOrder;
use App\Modules\Inventory\Actions\CreateRecipe;
use App\Modules\Inventory\Actions\CreateRecipeVersion;
use App\Modules\Inventory\Actions\RecordInventoryTransaction;
use App\Modules\Inventory\Actions\ReverseOrderInventoryConsumption;
use App\Modules\Inventory\Data\InventoryMovementData;
use App\Modules\Inventory\Data\InventoryTransactionData;
use App\Modules\Inventory\Data\RecipeIngredientData;
use App\Modules\Inventory\Data\RecipeVersionData;
use App\Modules\Inventory\Enums\InventorySourceType;
use App\Modules\Inventory\Enums\InventoryTransactionType;
use App\Modules\Inventory\Models\InventoryCategory;
use App\Modules\Inventory\Models\InventoryItem;
use App\Modules\Inventory\Models\InventoryUnit;
use App\Modules\Inventory\Support\DecimalMath;
use App\Modules\Orders\Actions\CreateOrder;
use App\Modules\Orders\Actions\UpdateOrder;
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
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

final class OrderConsumptionRecoveryTest extends TestCase
{
    use RefreshDatabase;

    public function test_configuration_versions_preserve_exact_components_and_previous_formula(): void
    {
        [$admin,$product,$item,$unit] = $this->fixture();
        $d = $this->configure($admin, $product, $item, $unit, '55.000001');
        $old = $d->currentVersion;
        $this->assertSame('55.000001', $old->components->sole()->base_quantity);
        $d = $this->configure($admin, $product, $item, $unit, '60');
        $this->assertSame(2, $d->currentVersion->version);
        $this->assertFalse($old->fresh()->is_active);
        $this->assertSame('55.000001', $old->fresh()->components->sole()->base_quantity);
        DB::statement('SET CONSTRAINTS ALL IMMEDIATE');
    }

    public function test_missing_configuration_blocks_delivery_atomically_but_explicit_no_inventory_is_known_zero(): void
    {
        [$a,$p] = $this->fixture();
        $o = $this->order($a, $p);
        try {
            app(UpdateOrderStatus::class)->execute($o, OrderStatus::Delivered, $a->id);
            $this->fail('Missing configuration must block delivery');
        } catch (ValidationException) {
            $this->assertNotSame(OrderStatus::Delivered, $o->fresh()->status);
        }
        $this->assertDatabaseCount('order_inventory_consumptions', 0);
        app(ConfigureProductInventoryConsumption::class)->execute($p->id, 'no_inventory', [], $a->id);
        app(UpdateOrderStatus::class)->execute($o, OrderStatus::Delivered, $a->id);
        $fact = DB::table('order_inventory_consumptions')->sole();
        $this->assertSame(0, $fact->total_cost);
        $this->assertNull($fact->inventory_transaction_id);
        $this->assertSame('no_inventory', json_decode($fact->products_snapshot, true)[0]['mode']);
        $this->assertSame('complete', $fact->cost_coverage);
        DB::statement('SET CONSTRAINTS ALL IMMEDIATE');
    }

    public function test_delivery_retry_cost_snapshot_reversal_and_future_prices(): void
    {
        [$a,$p,$i,$u] = $this->fixture();
        $this->receipt($a, $i, '1000', 10000);
        $this->receipt($a, $i, '1000', 20000);
        $this->configure($a, $p, $i, $u, '55');
        $o = $this->order($a, $p);
        app(UpdateOrderStatus::class)->execute($o, OrderStatus::Delivered, $a->id);
        $c = app(ConsumeDeliveredOrder::class)->execute($o->id, now(), $a->id);
        $this->assertSame(2475, $c->total_cost);
        $this->assertSame('165.000000', $c->components->sole()->consumed_base_quantity);
        $this->assertSame('Sold filling', $c->components->sole()->product_name_snapshot);
        $this->assertSame('-165.000000', $c->transaction->movements->sole()->quantity);
        $this->assertSame(-2475, $c->transaction->costLayers->sole()->total_cost);
        $this->assertDatabaseCount('order_inventory_consumptions', 1);
        $this->receipt($a, $i, '1000', 60000);
        $p->update(['name' => 'New name']);
        $i->update(['name' => 'Renamed physical filling']);
        $this->configure($a, $p, $i, $u, '80');
        $this->assertSame(2475, $c->fresh()->total_cost);
        app(UpdateOrderStatus::class)->execute($o->fresh(), OrderStatus::Cancelled, $a->id);
        $r = app(ReverseOrderInventoryConsumption::class)->execute($o->id, now(), $a->id);
        $this->assertSame('165.000000', $r->transaction->movements->sole()->quantity);
        $this->assertSame(2475, $r->transaction->costLayers->sole()->total_cost);
        $this->assertDatabaseCount('order_inventory_consumption_reversals', 1);
        DB::statement('SET CONSTRAINTS ALL IMMEDIATE');
        $this->expectException(ValidationException::class);
        app(UpdateOrderStatus::class)->execute($o->fresh(), OrderStatus::Delivered, $a->id);
    }

    public function test_unknown_valuation_consumes_physically_without_inventing_zero(): void
    {
        [$a,$p,$i,$u] = $this->fixture();
        $this->configure($a, $p, $i, $u, '55');
        $o = $this->order($a, $p);
        app(UpdateOrderStatus::class)->execute($o, OrderStatus::Delivered, $a->id);
        $c = app(ConsumeDeliveredOrder::class)->execute($o->id, now(), $a->id);
        $this->assertNull($c->total_cost);
        $this->assertNull($c->components->sole()->allocated_cost);
        $this->assertSame('unknown', $c->cost_coverage);
        $this->assertSame('no_cost_history', $c->components->sole()->fallback);
        $this->assertCount(0, $c->transaction->costLayers);
        DB::statement('SET CONSTRAINTS ALL IMMEDIATE');
    }

    public function test_configuration_rejects_ineligible_items_zero_and_duplicate_components(): void
    {
        [$a,$p,$i,$u] = $this->fixture();
        foreach (['0', '-1', '1.0000001'] as $q) {
            try {
                $this->configure($a, $p, $i, $u, $q);
                $this->fail('Invalid quantity');
            } catch (ValidationException) {
            }
        }
        $i->update(['order_consumption_enabled' => false]);
        $this->expectException(ValidationException::class);
        $this->configure($a, $p, $i, $u, '1');
    }

    public function test_configuration_routes_are_admin_only_and_canonical_products_remains_available(): void
    {
        [$a,$p] = $this->fixture();
        $url = '/admin/inventory/product-consumption/'.$p->id;
        $this->actingAs($a)->get('/admin/settings/products')->assertOk();
        $this->get($url)->assertOk();
        $this->post($url, ['mode' => 'no_inventory', 'components' => []])->assertRedirect();
        foreach (['waiter', 'cashier'] as $role) {
            $user = User::factory()->create(['staff_profile' => $role]);
            $this->actingAs($user)->get($url)->assertForbidden();
            $this->post($url, ['mode' => 'no_inventory', 'components' => []])->assertForbidden();
        }
    }

    public function test_explicit_mode_is_immutable_and_duplicates_are_rejected(): void
    {
        [$a,$p,$i,$u] = $this->fixture();
        $component = ['inventory_item_id' => $i->id, 'unit_id' => $u->id, 'quantity' => '1'];
        try {
            app(ConfigureProductInventoryConsumption::class)->execute($p->id, 'consume', [$component, $component], $a->id);
            $this->fail('Duplicate item');
        } catch (ValidationException) {
        }
        $this->assertDatabaseCount('inventory_product_consumption_definitions', 0);
        app(ConfigureProductInventoryConsumption::class)->execute($p->id, 'no_inventory', [], $a->id);
        $this->expectException(ValidationException::class);
        $this->configure($a, $p, $i, $u, '1');
    }

    public function test_multiple_components_unknown_partial_and_negative_fallback_coverage(): void
    {
        [$a,$p,$i,$u] = $this->fixture();
        $other = $i->replicate();
        $other->name = 'Wrapper';
        $other->save();
        $this->receipt($a, $i, '100', 1000);
        app(ConfigureProductInventoryConsumption::class)->execute($p->id, 'consume', [
            ['inventory_item_id' => $i->id, 'unit_id' => $u->id, 'quantity' => '55'],
            ['inventory_item_id' => $other->id, 'unit_id' => $u->id, 'quantity' => '1'],
        ], $a->id);
        $o = $this->order($a, $p);
        app(UpdateOrderStatus::class)->execute($o, OrderStatus::Delivered, $a->id);
        $c = app(ConsumeDeliveredOrder::class)->execute($o->id, now(), $a->id);
        $this->assertNull($c->total_cost);
        $this->assertSame('partial', $c->cost_coverage);
        $this->assertSame(1650, $c->components->where('inventory_item_id', $i->id)->sole()->allocated_cost);
        $this->assertSame('negative_stock_latest_basis', $c->components->where('inventory_item_id', $i->id)->sole()->fallback);
        $this->assertCount(2, $c->transaction->movements);
        $this->assertCount(1, $c->transaction->costLayers);
        app(UpdateOrderStatus::class)->execute($o->fresh(), OrderStatus::Cancelled, $a->id);
        $this->assertCount(1, app(ReverseOrderInventoryConsumption::class)->execute($o->id, now(), $a->id)->transaction->costLayers);
        DB::statement('SET CONSTRAINTS ALL IMMEDIATE');
    }

    public function test_commercial_units_do_not_depend_on_empanada_pricing_and_aggregate_shared_items(): void
    {
        [$a,$p,$i,$u] = $this->fixture();
        $p->update(['pricing_family' => 'empanada']);
        foreach (['unit', 'half_dozen', 'dozen'] as $tier) {
            DB::table('raw_price_discounts')->insert(['pricing_mode' => $tier, 'amount' => 0]);
        }
        $this->receipt($a, $i, '1000', 16650);
        $this->configure($a, $p, $i, $u, '1');
        $o = $this->order($a, $p);
        $other = $p->replicate();
        $other->name = 'Other sale';
        $other->slug = 'other-sale';
        $other->save();
        $this->configure($a, $other, $i, $u, '2');
        $o->items()->create(['product_id' => $other->id, 'product_name' => 'Other sale', 'pricing_mode' => 'unit', 'quantity' => 1, 'units_count' => 1, 'unit_price' => 100, 'line_total' => 100, 'regular_price_total' => 100]);
        app(UpdateOrderStatus::class)->execute($o, OrderStatus::Delivered, $a->id);
        $c = app(ConsumeDeliveredOrder::class)->execute($o->id, now(), $a->id);
        $this->assertCount(1, $c->transaction->movements);
        $this->assertCount(2, $c->components);
        $this->assertSame('-5.000000', $c->transaction->movements->sole()->quantity);
        $this->assertSame(83, $c->total_cost);
        $this->assertSame(83, $c->components->sum('allocated_cost'));
        DB::statement('SET CONSTRAINTS ALL IMMEDIATE');
    }

    public function test_combined_composition_consumes_each_sold_unit_and_no_inventory_is_snapshotted(): void
    {
        [$a,$p,$i,$u] = $this->fixture();
        $this->receipt($a, $i, '1000', 1000);
        $this->configure($a, $p, $i, $u, '55');
        $other = $p->replicate();
        $other->name = 'Not tracked';
        $other->slug = 'not-tracked';
        $other->save();
        app(ConfigureProductInventoryConsumption::class)->execute($other->id, 'no_inventory', [], $a->id);
        $o = $this->order($a, $p);
        $line = $o->items->sole();
        $line->compositions()->create(['product_id' => $p->id, 'product_name' => 'Sold formula', 'quantity' => 2]);
        $line->compositions()->create(['product_id' => $other->id, 'product_name' => 'Sold no inventory', 'quantity' => 1]);
        app(UpdateOrderStatus::class)->execute($o, OrderStatus::Delivered, $a->id);
        $c = app(ConsumeDeliveredOrder::class)->execute($o->id, now(), $a->id);
        $this->assertSame('110.000000', $c->components->sole()->consumed_base_quantity);
        $this->assertSame(110, $c->total_cost);
        $this->assertCount(2, $c->products_snapshot);
        $this->assertSame('no_inventory', $c->products_snapshot[1]['mode']);
        DB::statement('SET CONSTRAINTS ALL IMMEDIATE');
    }

    public function test_partial_physical_valuation_is_unknown_not_free(): void
    {
        [$a,$p,$i,$u] = $this->fixture();
        $this->receipt($a, $i, '100', 1000);
        app(RecordInventoryTransaction::class)->execute(new InventoryTransactionData(InventoryTransactionType::Adjustment, now(), [new InventoryMovementData($i->id, '100', null)], InventorySourceType::Adjustment, (string) Str::uuid()), $a->id);
        $this->configure($a, $p, $i, $u, '1');
        $o = $this->order($a, $p);
        app(UpdateOrderStatus::class)->execute($o, OrderStatus::Delivered, $a->id);
        $c = app(ConsumeDeliveredOrder::class)->execute($o->id, now(), $a->id);
        $this->assertNull($c->total_cost);
        $this->assertSame('partial_valuation', $c->components->sole()->fallback);
        DB::statement('SET CONSTRAINTS ALL IMMEDIATE');
    }

    public function test_complete_fallback_coverage_remains_unreliable_and_reversal_preserves_warning(): void
    {
        [$a,$p,$i,$u] = $this->fixture();
        $this->receipt($a, $i, '100', 1000);
        $this->configure($a, $p, $i, $u, '55');
        $o = $this->order($a, $p);
        app(UpdateOrderStatus::class)->execute($o, OrderStatus::Delivered, $a->id);
        $c = app(ConsumeDeliveredOrder::class)->execute($o->id, now(), $a->id);
        $this->assertSame(1650, $c->total_cost);
        $this->assertSame('unreliable', $c->cost_coverage);
        app(UpdateOrderStatus::class)->execute($o->fresh(), OrderStatus::Cancelled, $a->id);
        $r = app(ReverseOrderInventoryConsumption::class)->execute($o->id, now(), $a->id);
        $this->assertSame('negative_stock_latest_basis', $r->transaction->costLayers->sole()->valuation_warning);
        DB::statement('SET CONSTRAINTS ALL IMMEDIATE');
    }

    public function test_full_edit_delivery_triggers_consumption_and_economic_edits_are_locked(): void
    {
        [$a,$p,$i,$u] = $this->fixture();
        $this->receipt($a, $i, '1000', 1000);
        $this->configure($a, $p, $i, $u, '55');
        $o = $this->order($a, $p);
        $data = new OrderCheckoutData(items: [['product_id' => $p->id, 'quantity' => 3]], addons: [], condition: OrderCondition::Cooked, deliveryType: DeliveryType::DineIn, deliveryPeriod: null, deliveryDate: null, deliveryTime: null, deliveryAddress: null, notes: 'Allowed note', customerId: null, customerName: null, customerPhone: null, customerPhoneNormalized: null, customerAddress: null, paymentStatus: PaymentStatus::Unpaid);
        $action = app(UpdateOrder::class);
        $o = $action->execute($o, $data, OrderStatus::Delivered, $o->edit_version, $a->id, null);
        $this->assertDatabaseCount('order_inventory_consumptions', 1);
        $o = $action->execute($o, $data, OrderStatus::Delivered, $o->edit_version, $a->id, 'Note only');
        $this->assertSame('Allowed note', $o->notes);
        $changed = new OrderCheckoutData(...array_replace(get_object_vars($data), ['items' => [['product_id' => $p->id, 'quantity' => 4]]]));
        $this->expectException(ValidationException::class);
        $action->execute($o, $changed, OrderStatus::Delivered, $o->edit_version, $a->id, 'Economic change');
    }

    public function test_delivery_pickup_and_all_sources_remain_independent_of_customer_and_costing(): void
    {
        [$a,$p,$i,$u] = $this->fixture();
        $this->receipt($a, $i, '1000', 1000);
        $this->configure($a, $p, $i, $u, '1');
        foreach ([[OrderSource::Admin, DeliveryType::Delivery], [OrderSource::PublicCheckout, DeliveryType::Pickup], [OrderSource::Pos, DeliveryType::DineIn]] as [$source,$type]) {
            $o = $this->order($a, $p);
            if ($type !== DeliveryType::DineIn) {
                $o->update(['customer_id' => Customer::query()->create(['name' => 'Real guest'])->id, 'source' => $source, 'delivery_type' => $type, 'customer_name_snapshot' => 'Real guest', 'customer_phone_snapshot' => $source === OrderSource::PublicCheckout ? '12345678' : null, 'delivery_date' => now()->toDateString(), 'delivery_period' => 'night', 'delivery_address_snapshot' => $type === DeliveryType::Delivery ? 'Physical address' : null]);
            }
            app(UpdateOrderStatus::class)->execute($o, OrderStatus::Delivered, $a->id);
            if ($type === DeliveryType::DineIn) {
                $this->assertNull($o->fresh()->customer_id);
            }
        }
        $this->assertDatabaseCount('order_inventory_consumptions', 3);
        DB::statement('SET CONSTRAINTS ALL IMMEDIATE');
    }

    public function test_order_consumes_produced_component_without_expanding_its_recipe(): void
    {
        [$a,$p,$i,$u] = $this->fixture();
        $i->update(['production_output_enabled' => true]);
        $raw = $i->replicate();
        $raw->name = 'Raw onion';
        $raw->kind = 'raw_material';
        $raw->recipe_input_enabled = true;
        $raw->save();
        $this->receipt($a, $raw, '1000', 2150);
        $this->receipt($a, $i, '1000', 10000);
        $recipe = app(CreateRecipe::class)->execute('Filling formula', $a->id);
        app(CreateRecipeVersion::class)->execute($recipe->id, new RecipeVersionData($i->id, $u->id, '500', [new RecipeIngredientData($raw->id, $u->id, '1000')]), $a->id);
        $this->configure($a, $p, $i, $u, '55');
        $o = $this->order($a, $p);
        app(UpdateOrderStatus::class)->execute($o, OrderStatus::Delivered, $a->id);
        $this->assertSame('1000.000000', DecimalMath::normalize((string) $raw->stockMovements()->sum('quantity'), 6));
        $c = app(ConsumeDeliveredOrder::class)->execute($o->id, now(), $a->id);
        $this->assertSame($i->id, $c->components->sole()->inventory_item_id);
        DB::statement('SET CONSTRAINTS ALL IMMEDIATE');
    }

    public function test_configured_dozen_keeps_stock_and_historical_base_unit_in_units(): void
    {
        [$a,$p,$i,$u] = $this->fixture();
        $base = InventoryUnit::query()->where('code', 'unit')->sole();
        $dozen = InventoryUnit::query()->where('code', 'dozen')->sole();
        $i->update(['unit_family' => 'count', 'base_unit_id' => $base->id, 'default_input_unit_id' => $dozen->id, 'quantity_precision' => 0]);
        $this->receipt($a, $i, '100', 10000);
        $this->configure($a, $p, $i, $dozen, '0.5');
        $o = $this->order($a, $p);
        app(UpdateOrderStatus::class)->execute($o, OrderStatus::Delivered, $a->id);
        $c = app(ConsumeDeliveredOrder::class)->execute($o->id, now(), $a->id);
        $this->assertSame('18.000000', $c->components->sole()->consumed_base_quantity);
        $this->assertSame('dozen', $c->components->sole()->unit_code_snapshot);
        $this->assertSame('unit', $c->components->sole()->base_unit_code_snapshot);
        $this->assertSame(1800, $c->total_cost);
        DB::statement('SET CONSTRAINTS ALL IMMEDIATE');
    }

    public function test_status_logs_preserve_cancelled_message_and_existing_context(): void
    {
        [$a,$p] = $this->fixture();
        app(ConfigureProductInventoryConsumption::class)->execute($p->id, 'no_inventory', [], $a->id);
        $o = $this->order($a, $p);
        Log::spy();
        app(UpdateOrderStatus::class)->execute($o, OrderStatus::Delivered, $a->id);
        Log::shouldHaveReceived('info')->with('[Orders] order.status_changed', [
            'module' => 'Orders', 'order_id' => $o->public_id, 'admin_id' => $a->id, 'from' => 'waiting', 'to' => 'delivered',
        ])->once();
        app(UpdateOrderStatus::class)->execute($o, OrderStatus::Cancelled, $a->id);
        Log::shouldHaveReceived('info')->with('[Orders] order.cancelled', [
            'module' => 'Orders', 'order_id' => $o->public_id, 'admin_id' => $a->id, 'from' => 'delivered', 'to' => 'cancelled',
        ])->once();
        $this->assertSame(OrderStatus::Cancelled, $o->fresh()->status);
        $this->assertDatabaseCount('order_inventory_consumptions', 1);
        $this->assertDatabaseCount('order_inventory_consumption_reversals', 1);
        DB::statement('SET CONSTRAINTS ALL IMMEDIATE');
    }

    public function test_unclassified_eligible_item_keeps_required_category_in_configuration(): void
    {
        [$a,$p,$i,$u] = $this->fixture();
        $i->update(['kind' => null]);
        $this->actingAs($a)->get('/admin/inventory/product-consumption/'.$p->id)->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('items.0.kind', null)->where('items.0.category.name', 'Physical')
                ->where('items.0.order_consumption_enabled', true));
        $this->configure($a, $p, $i, $u, '55');
        $before = DB::table('inventory_items')->where('id', $i->id)->first();
        try {
            DB::transaction(fn () => DB::table('inventory_items')->where('id', $i->id)->update(['inventory_category_id' => null]));
            $this->fail('Category cannot be null in supported persistence');
        } catch (QueryException $e) {
            $this->assertSame('23502', $e->errorInfo[0]);
        }
        $this->assertEquals($before, DB::table('inventory_items')->where('id', $i->id)->first());
        DB::statement('SET CONSTRAINTS ALL IMMEDIATE');
    }

    /** @return array{User,Product,InventoryItem,InventoryUnit} */
    private function fixture(): array
    {
        $a = User::factory()->administrator()->create();
        $category = Category::query()->create(['name' => 'Sale', 'slug' => 'sale']);
        $p = Product::query()->create(['category_id' => $category->id, 'name' => 'Sold filling', 'slug' => 'filling', 'pricing_family' => 'unit', 'unit_price' => 2500, 'half_dozen_price' => 2500, 'dozen_price' => 2500, 'is_available' => true]);
        $u = InventoryUnit::query()->where('code', 'g')->sole();
        $c = InventoryCategory::query()->create(['name' => 'Physical']);
        $i = InventoryItem::query()->create(['inventory_category_id' => $c->id, 'name' => 'Filling', 'tracks_stock' => true, 'unit_family' => 'weight', 'base_unit_id' => $u->id, 'default_input_unit_id' => $u->id, 'quantity_precision' => 6, 'is_active' => true, 'kind' => 'semi_finished', 'order_consumption_enabled' => true]);

        return [$a, $p, $i, $u];
    }

    private function configure(User $a, Product $p, InventoryItem $i, InventoryUnit $u, string $q): mixed
    {
        return app(ConfigureProductInventoryConsumption::class)->execute($p->id, 'consume', [['inventory_item_id' => $i->id, 'quantity' => $q, 'unit_id' => $u->id]], $a->id);
    }

    private function order(User $a, Product $p): Order
    {
        return app(CreateOrder::class)->execute(new OrderCheckoutData(items: [['product_id' => $p->id, 'quantity' => 3]], addons: [], condition: OrderCondition::Cooked, deliveryType: DeliveryType::DineIn, deliveryPeriod: null, deliveryDate: null, deliveryTime: null, deliveryAddress: null, notes: null, customerId: null, customerName: null, customerPhone: null, customerPhoneNormalized: null, customerAddress: null, paymentStatus: PaymentStatus::Unpaid), OrderSource::Pos, $a->id);
    }

    private function receipt(User $a, InventoryItem $i, string $q, int $cost): void
    {
        app(RecordInventoryTransaction::class)->execute(new InventoryTransactionData(InventoryTransactionType::Adjustment, CarbonImmutable::parse('2026-10-01'), [new InventoryMovementData($i->id, $q, $cost)], InventorySourceType::Adjustment, (string) Str::uuid()), $a->id);
    }
}
