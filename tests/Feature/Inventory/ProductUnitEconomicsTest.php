<?php

namespace Tests\Feature\Inventory;

use App\Modules\Inventory\Actions\AssignProductCostingPolicy;
use App\Modules\Inventory\Actions\ConfigureCostingPolicy;
use App\Modules\Inventory\Actions\ConfigureProductInventoryConsumption;
use App\Modules\Inventory\Actions\CreateRecipe;
use App\Modules\Inventory\Actions\CreateRecipeVersion;
use App\Modules\Inventory\Data\RecipeIngredientData;
use App\Modules\Inventory\Data\RecipeVersionData;
use App\Modules\Inventory\Models\InventoryUnit;
use App\Modules\Inventory\Queries\BuildProductUnitEconomics;
use App\Modules\Orders\Actions\CreateOrder;
use App\Modules\Orders\Actions\UpdateOrderStatus;
use App\Modules\Orders\Data\OrderCheckoutData;
use App\Modules\Orders\Enums\DeliveryType;
use App\Modules\Orders\Enums\OrderCondition;
use App\Modules\Orders\Enums\OrderSource;
use App\Modules\Orders\Enums\OrderStatus;
use App\Modules\Orders\Enums\PaymentStatus;
use App\Modules\Orders\Pricing\OrderPricingEngine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\CostingFixtures;
use Tests\TestCase;

final class ProductUnitEconomicsTest extends TestCase
{
    use CostingFixtures, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->costingFixture();
    }

    private function assign(array $overrides = []): void
    {
        $p = app(ConfigureCostingPolicy::class)->execute(null, array_replace($this->policyPayload(), $overrides));
        app(AssignProductCostingPolicy::class)->execute($this->product->id, $p->id);
    }

    private function estimate(int $quantity = 1): array
    {
        return app(BuildProductUnitEconomics::class)->build($this->product, $quantity);
    }

    public function test_missing_consumption_and_missing_policy_are_explicit_and_never_zero(): void
    {
        $r = $this->estimate();
        $p = $r['presentations'][0];
        $this->assertSame('missing_config', $r['physical_status']);
        $this->assertSame('missing', $r['policy_status']);
        $this->assertSame('incomplete', $p['status']);
        foreach (['direct_cost', 'packaging_cost', 'overhead_estimate', 'estimated_total_cost', 'estimated_margin', 'estimated_margin_percentage_bps'] as $key) {
            $this->assertNull($p[$key]);
        }
        $this->assertContains('consumption_missing', $p['warnings']);
        $this->assertContains('policy_missing', $p['warnings']);
        $this->assertSame('incomplete', $p['direct_coverage']['status']);
        $this->actingAs($this->admin)->get('/admin/inventory/costing?product_id='.$this->product->id)->assertOk()->assertInertia(fn (AssertableInertia $page) => $page->where('economics.physical_status', 'missing_config')->where('products.0.pricing_family', 'empanada'));
    }

    public function test_explicit_no_inventory_is_known_zero_but_policy_is_not_inferred(): void
    {
        app(ConfigureProductInventoryConsumption::class)->execute($this->product->id, 'no_inventory', [], $this->admin->id);
        $other = app(ConfigureCostingPolicy::class)->execute(null, $this->policyPayload());
        $r = $this->estimate();
        $this->assertNull($r['costing_policy']);
        $this->assertSame(0, $r['presentations'][0]['direct_cost']);
        app(AssignProductCostingPolicy::class)->execute($this->product->id, $other->id);
        $p = $this->estimate()['presentations'][0];
        $this->assertSame('complete', $p['status']);
        $this->assertSame(0, $p['direct_cost']);
        $this->assertSame(0, $p['packaging_cost']);
        $this->assertSame(0, $p['overhead_estimate']);
        $this->assertSame(12000, $p['estimated_margin']);
        $this->assertSame(10000, $p['estimated_margin_percentage_bps']);
    }

    public function test_empanada_presentations_use_real_quotes_scale_direct_cost_and_specific_packaging(): void
    {
        $this->receive($this->filling, '10000', 100000);
        $this->receive($this->box, '100', 70000);
        $this->consumption();
        $this->assign();
        $r = $this->estimate();
        $this->assertSame('empanada', $r['pricing_family']);
        $this->assertSame([1, 6, 12], array_column($r['presentations'], 'commercial_quantity'));
        foreach ([[0, 1, 12000, 550, 0, 55, 605, 11395], [1, 6, 65000, 3300, 700, 400, 4400, 60600], [2, 12, 120000, 6600, 1400, 800, 8800, 111200]] as [$index,$q,$price,$direct,$packaging,$overhead,$total,$margin]) {
            $p = $r['presentations'][$index];
            $quote = app(OrderPricingEngine::class)->quote([['product_id' => $this->product->id, 'quantity' => $q]], OrderCondition::Cooked);
            $this->assertSame($quote->total, $p['selling_price']);
            $this->assertSame($price, $p['selling_price']);
            foreach (['direct_cost' => $direct, 'packaging_cost' => $packaging, 'overhead_estimate' => $overhead, 'estimated_total_cost' => $total, 'estimated_margin' => $margin] as $key => $value) {
                $this->assertSame($value, $p[$key]);
            }
            $this->assertSame('complete', $p['status']);
        }
        $this->assertSame('2.000001', $r['presentations'][2]['packaging_components'][0]['quantity']);
        $this->assertSame('unit', $r['presentations'][2]['packaging_components'][0]['unit_code']);
    }

    public function test_unit_family_pizza_economics_use_unit_price_and_not_category_or_empanada_discounts(): void
    {
        $this->product->update(['pricing_family' => 'unit', 'half_dozen_price' => 12000, 'dozen_price' => 12000, 'name' => 'Pizza Muzza']);
        $this->product->category->update(['name' => 'Empanadas']);
        $this->receive($this->filling, '10000', 100000);
        $this->receive($this->box, '100', 70000);
        $this->consumption('430');
        $this->assign(['overhead_basis_points' => 2400, 'presentations' => [['commercial_quantity' => 1, 'packaging_mode' => 'components', 'components' => [['inventory_item_id' => $this->box->id, 'quantity' => '1', 'unit_id' => $this->unit->id]]]]]);
        $r = $this->estimate();
        $this->assertSame('unit', $r['pricing_family']);
        $this->assertCount(1, $r['presentations']);
        $p = $r['presentations'][0];
        foreach (['selling_price' => 12000, 'direct_cost' => 4300, 'packaging_cost' => 700, 'overhead_estimate' => 1200, 'estimated_total_cost' => 6200, 'estimated_margin' => 5800, 'estimated_margin_percentage_bps' => 4833] as $key => $value) {
            $this->assertSame($value, $p[$key]);
        }
        $q = app(OrderPricingEngine::class)->quote([['product_id' => $this->product->id, 'quantity' => 1]], OrderCondition::Raw);
        $this->assertSame(12000, $q->total);
        $this->assertSame('unit', $p['pricing_mode']);
    }

    public function test_explicit_larger_unit_quantity_preserves_real_unit_pricing_without_dozen_modes(): void
    {
        $this->product->update(['pricing_family' => 'unit', 'half_dozen_price' => 12000, 'dozen_price' => 12000]);
        $this->receive($this->filling, '10000', 100000);
        $this->consumption();
        $this->assign(['presentations' => [['commercial_quantity' => 6, 'packaging_mode' => 'none', 'components' => []]]]);
        $r = $this->estimate(6);
        $this->assertCount(1, $r['presentations']);
        $p = $r['presentations'][0];
        $this->assertSame(6, $p['commercial_quantity']);
        $this->assertSame('unit', $p['pricing_mode']);
        $this->assertSame(72000, $p['selling_price']);
        $this->assertSame(3300, $p['direct_cost']);
        $this->assertSame(3630, $p['estimated_total_cost']);
    }

    public function test_exact_quantity_conversion_and_reference_mass_do_not_reinterpret_physical_stock(): void
    {
        $this->receive($this->filling, '10000', 100000);
        $kg = InventoryUnit::query()->where('code', 'kg')->sole();
        $this->consumption('0.055000', $kg);
        $this->assign();
        $this->assertSame(550, $this->estimate()['presentations'][0]['direct_cost']);
        $this->box->update(['reference_mass_grams_per_unit' => '25']);
        $this->receive($this->box, '100', 70000);
        $p = $this->estimate()['presentations'][1];
        $this->assertSame(700, $p['packaging_cost']);
        $this->assertSame('1.000000', $p['packaging_components'][0]['base_quantity']);
        $this->assertSame('100.000000', DB::table('inventory_stock_movements')->where('inventory_item_id', $this->box->id)->sum('quantity'));
    }

    public function test_multiple_component_aggregate_rounding_does_not_round_each_unit_cost_before_scaling(): void
    {
        $this->receive($this->filling, '3', 1);
        $this->receive($this->box, '3', 1);
        $this->box->update(['order_consumption_enabled' => true]);
        app(ConfigureProductInventoryConsumption::class)->execute($this->product->id, 'consume', [
            ['inventory_item_id' => $this->filling->id, 'quantity' => '1', 'unit_id' => $this->gram->id],
            ['inventory_item_id' => $this->box->id, 'quantity' => '1', 'unit_id' => $this->unit->id],
        ], $this->admin->id);
        $this->assign();
        $p = $this->estimate()['presentations'][0];
        $this->assertSame(1, $p['direct_cost']);
        $this->assertSame(2, $p['direct_coverage']['known_components']);
        $this->assertSame(2, $p['direct_coverage']['total_components']);
        $this->assertSame('complete', $p['direct_coverage']['status']);
    }

    public function test_unknown_component_keeps_partial_coverage_and_never_creates_an_exact_margin(): void
    {
        $this->receive($this->filling, '1000', 10000);
        $this->box->update(['order_consumption_enabled' => true]);
        app(ConfigureProductInventoryConsumption::class)->execute($this->product->id, 'consume', [
            ['inventory_item_id' => $this->filling->id, 'quantity' => '55', 'unit_id' => $this->gram->id],
            ['inventory_item_id' => $this->box->id, 'quantity' => '1', 'unit_id' => $this->unit->id],
        ], $this->admin->id);
        $this->assign();
        $p = $this->estimate()['presentations'][0];
        $this->assertSame(550, $p['known_direct_cost']);
        $this->assertNull($p['direct_cost']);
        $this->assertSame(1, $p['direct_coverage']['known_components']);
        $this->assertSame(2, $p['direct_coverage']['total_components']);
        $this->assertSame('incomplete', $p['status']);
        $this->assertNull($p['estimated_margin']);
        $this->assertContains('direct_cost_incomplete', $p['warnings']);
    }

    public function test_packaging_unknown_and_missing_presentation_are_not_explicit_no_packaging(): void
    {
        $this->receive($this->filling, '10000', 100000);
        $this->consumption();
        $this->assign(['presentations' => [$this->policyPayload()['presentations'][0], $this->policyPayload()['presentations'][1]]]);
        $r = $this->estimate();
        $this->assertSame('none', $r['presentations'][0]['packaging_status']);
        $this->assertSame(0, $r['presentations'][0]['packaging_cost']);
        $this->assertNull($r['presentations'][1]['packaging_cost']);
        $this->assertContains('packaging_cost_incomplete', $r['presentations'][1]['warnings']);
        $this->assertSame('missing', $r['presentations'][2]['packaging_status']);
        $this->assertNull($r['presentations'][2]['estimated_margin']);
        $this->assertContains('packaging_missing', $r['presentations'][2]['warnings']);
    }

    public function test_insufficient_stock_and_partial_valuation_are_unreliable_not_complete(): void
    {
        $this->receive($this->filling, '55', 550);
        $this->consumption();
        $this->assign();
        $p = $this->estimate()['presentations'][1];
        $this->assertSame('unreliable', $p['status']);
        $this->assertSame('insufficient_stock', $p['direct_components'][0]['warning']);
        $this->assertNull($p['estimated_margin']);
        $this->receive($this->filling, '1000', null);
        $p = $this->estimate()['presentations'][0];
        $this->assertSame('unreliable', $p['direct_coverage']['status']);
        $this->assertSame('partial', $p['direct_components'][0]['valuation_coverage']);
        $this->assertNull($p['direct_cost']);
    }

    #[DataProvider('sharedStockDemand')]
    public function test_shared_direct_and_packaging_demand_controls_coverage_without_stock_writes(string $stock, string $packagingQuantity, string $unitCode, bool $sufficient): void
    {
        $this->box->update(['order_consumption_enabled' => true]);
        $this->receive($this->box, $stock, 800);
        app(ConfigureProductInventoryConsumption::class)->execute($this->product->id, 'consume', [
            ['inventory_item_id' => $this->box->id, 'quantity' => '1', 'unit_id' => $this->unit->id],
        ], $this->admin->id);
        $this->assign(['overhead_basis_points' => 0, 'presentations' => [
            ['commercial_quantity' => 1, 'packaging_mode' => 'none', 'components' => []],
            ['commercial_quantity' => 6, 'packaging_mode' => 'components', 'components' => [
                ['inventory_item_id' => $this->box->id, 'quantity' => $packagingQuantity, 'unit_id' => InventoryUnit::query()->where('code', $unitCode)->sole()->id],
            ]],
        ]]);
        $tables = ['inventory_transactions', 'inventory_stock_movements', 'inventory_cost_layers', 'order_inventory_consumptions', 'order_inventory_consumption_components', 'inventory_product_consumption_versions', 'inventory_product_consumption_components', 'financial_transactions', 'ledger_entries'];
        $snapshot = fn () => array_map(fn ($table) => DB::table($table)->orderBy('id')->get()->toJson(), $tables);
        $before = $snapshot();
        $r = $this->estimate();
        $half = $r['presentations'][1];
        $this->assertSame($sufficient ? 'complete' : 'unreliable', $half['status']);
        $this->assertSame($sufficient ? 'complete' : 'unreliable', $half['direct_coverage']['status']);
        $this->assertSame($sufficient ? 'complete' : 'unreliable', $half['packaging_coverage']['status']);
        if ($sufficient) {
            $this->assertSame(600, $half['direct_cost']);
            $this->assertSame(200, $half['packaging_cost']);
            $this->assertSame(800, $half['estimated_total_cost']);
            $this->assertSame(64200, $half['estimated_margin']);
        } else {
            $this->assertSame('insufficient_stock', $half['direct_components'][0]['warning']);
            $this->assertSame('insufficient_stock', $half['packaging_components'][0]['warning']);
            foreach (['direct_cost', 'packaging_cost', 'overhead_estimate', 'estimated_total_cost', 'estimated_margin', 'estimated_margin_percentage_bps'] as $key) {
                $this->assertNull($half[$key]);
            }
        }
        $this->assertSame('complete', $r['presentations'][0]['status']);
        $this->assertSame('6.000000', $half['direct_components'][0]['base_quantity']);
        $this->assertSame($unitCode === 'dozen' ? '2.000004' : '2.000000', $half['packaging_components'][0]['base_quantity']);
        $this->assertSame($before, $snapshot());
        DB::statement('SET CONSTRAINTS ALL IMMEDIATE');
    }

    public static function sharedStockDemand(): array
    {
        return [
            ['7', '2', 'unit', false],
            ['7.999999', '2', 'unit', false],
            ['8', '2', 'unit', true],
            ['8', '0.166667', 'dozen', false],
            ['8.000004', '0.166667', 'dozen', true],
        ];
    }

    public function test_inactive_policy_is_explicit_and_never_used_for_margin(): void
    {
        $this->receive($this->filling, '1000', 10000);
        $this->consumption();
        $this->assign();
        DB::table('inventory_costing_policies')->update(['is_active' => false]);
        $r = $this->estimate();
        $this->assertSame('inactive', $r['policy_status']);
        $this->assertSame(550, $r['presentations'][0]['direct_cost']);
        $this->assertNull($r['presentations'][0]['estimated_margin']);
        $this->assertContains('policy_inactive', $r['presentations'][0]['warnings']);
    }

    public function test_recipe_is_not_expanded_and_raw_price_changes_do_not_affect_semifinished_cost(): void
    {
        $this->filling->update(['production_output_enabled' => true]);
        $raw = $this->filling->replicate();
        $raw->name = 'Onion';
        $raw->kind = 'raw_material';
        $raw->recipe_input_enabled = true;
        $raw->save();
        $this->receive($raw, '1000', 2150);
        $this->receive($this->filling, '10000', 100000);
        $r = app(CreateRecipe::class)->execute('Filling formula', $this->admin->id);
        app(CreateRecipeVersion::class)->execute($r->id, new RecipeVersionData($this->filling->id, $this->gram->id, '500', [new RecipeIngredientData($raw->id, $this->gram->id, '1000')]), $this->admin->id);
        $this->consumption();
        $this->assign();
        $this->assertSame(550, $this->estimate()['presentations'][0]['direct_cost']);
        $this->receive($raw, '1000', 50000);
        $p = $this->estimate()['presentations'][0];
        $this->assertSame(550, $p['direct_cost']);
        $this->assertCount(1, $p['direct_components']);
        $this->assertSame($this->filling->id, $p['direct_components'][0]['inventory_item_id']);
    }

    public function test_current_valuation_changes_but_costing_reads_preserve_historical_cogs_and_all_ledgers(): void
    {
        $this->receive($this->filling, '10000', 100000);
        $this->consumption();
        $this->assign();
        $o = app(CreateOrder::class)->execute(new OrderCheckoutData(items: [['product_id' => $this->product->id, 'quantity' => 3]], addons: [], condition: OrderCondition::Cooked, deliveryType: DeliveryType::DineIn, deliveryPeriod: null, deliveryDate: null, deliveryTime: null, deliveryAddress: null, notes: null, customerId: null, customerName: null, customerPhone: null, customerPhoneNormalized: null, customerAddress: null, paymentStatus: PaymentStatus::Unpaid), OrderSource::Pos, $this->admin->id);
        app(UpdateOrderStatus::class)->execute($o, OrderStatus::Delivered, $this->admin->id);
        $before = DB::table('order_inventory_consumptions')->sole();
        $this->assertSame(1650, $before->total_cost);
        $old = $this->estimate()['presentations'][0]['direct_cost'];
        $this->receive($this->filling, '10000', 200000);
        $this->assertNotSame($old, $this->estimate()['presentations'][0]['direct_cost']);
        $this->assertEquals($before, DB::table('order_inventory_consumptions')->sole());
        $tables = ['inventory_stock_movements', 'inventory_cost_layers', 'inventory_transactions', 'order_inventory_consumptions', 'order_inventory_consumption_components', 'inventory_product_consumption_versions', 'inventory_product_consumption_components', 'financial_transactions', 'ledger_entries'];
        $snapshot = fn () => array_map(fn ($table) => DB::table($table)->orderBy('id')->get()->toJson(), $tables);
        $state = $snapshot();
        for ($n = 0; $n < 3; $n++) {
            $this->estimate();
        }
        $this->assertSame($state, $snapshot());
        DB::statement('SET CONSTRAINTS ALL IMMEDIATE');
    }

    #[DataProvider('overheadResults')]
    public function test_historical_overhead_formula_uses_direct_plus_packaging_not_selling_price(int $bp, int $expected): void
    {
        $this->receive($this->filling, '10000', 100000);
        $this->receive($this->box, '100', 70000);
        $this->consumption();
        $this->assign(['overhead_basis_points' => $bp]);
        $this->assertSame($expected, $this->estimate()['presentations'][1]['overhead_estimate']);
    }

    public static function overheadResults(): array
    {
        return [[0, 0], [1000, 400], [2500, 1000], [10000, 4000]];
    }

    public function test_unavailable_sellable_price_retains_cost_but_blocks_margin_with_reason(): void
    {
        $this->receive($this->filling, '1000', 10000);
        $this->consumption();
        $this->assign();
        $this->product->update(['is_available' => false]);
        $p = $this->estimate()['presentations'][0];
        $this->assertSame(605, $p['estimated_total_cost']);
        $this->assertNull($p['selling_price']);
        $this->assertNull($p['estimated_margin']);
        $this->assertSame('incomplete', $p['status']);
        $this->assertContains('pricing_unavailable', $p['warnings']);
    }
}
