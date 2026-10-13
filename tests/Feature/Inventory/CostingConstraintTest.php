<?php

namespace Tests\Feature\Inventory;

use App\Modules\Inventory\Actions\AssignProductCostingPolicy;
use App\Modules\Inventory\Actions\ConfigureCostingPolicy;
use App\Modules\Inventory\Models\InventoryTransaction;
use App\Modules\Inventory\Queries\BuildProductUnitEconomics;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\CostingFixtures;
use Tests\TestCase;

final class CostingConstraintTest extends TestCase
{
    use CostingFixtures, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->costingFixture();
    }

    public function test_explicit_unreliable_valuation_propagates_to_direct_and_packaging_and_blocks_margin(): void
    {
        foreach ([$this->filling, $this->box] as $i) {
            $t = InventoryTransaction::query()->create(['type' => 'adjustment', 'source_type' => 'adjustment', 'source_id' => (string) Str::uuid(), 'effective_date' => '2026-10-13', 'created_by' => $this->admin->id]);
            $m = $t->movements()->create(['inventory_item_id' => $i->id, 'quantity' => '10000', 'unit_id' => $i->base_unit_id]);
            $t->costLayers()->create(['inventory_item_id' => $i->id, 'stock_movement_id' => $m->id, 'quantity' => '10000', 'total_cost' => 100000, 'effective_date' => '2026-10-13', 'valuation_warning' => 'unreliable_input']);
        }
        $this->consumption();
        $p = app(ConfigureCostingPolicy::class)->execute(null, $this->policyPayload());
        app(AssignProductCostingPolicy::class)->execute($this->product->id, $p->id);
        $r = app(BuildProductUnitEconomics::class)->build($this->product)['presentations'][1];
        $this->assertSame('unreliable', $r['status']);
        $this->assertSame('unreliable', $r['direct_coverage']['status']);
        $this->assertSame('unreliable', $r['packaging_coverage']['status']);
        $this->assertSame('unreliable', $r['direct_components'][0]['valuation_coverage']);
        $this->assertSame('unreliable', $r['packaging_components'][0]['valuation_coverage']);
        $this->assertNull($r['direct_cost']);
        $this->assertNull($r['packaging_cost']);
        $this->assertNull($r['estimated_total_cost']);
        $this->assertNull($r['estimated_margin']);
        DB::statement('SET CONSTRAINTS ALL IMMEDIATE');
    }

    public function test_real_zero_valuation_is_known_zero_not_missing_history(): void
    {
        $this->receive($this->filling, '10000', 0);
        $this->consumption();
        $p = app(ConfigureCostingPolicy::class)->execute(null, $this->policyPayload());
        app(AssignProductCostingPolicy::class)->execute($this->product->id, $p->id);
        $r = app(BuildProductUnitEconomics::class)->build($this->product)['presentations'][0];
        $this->assertSame('complete', $r['status']);
        $this->assertSame(0, $r['direct_cost']);
        $this->assertSame(12000, $r['estimated_margin']);
    }

    #[DataProvider('invalidSql')]
    public function test_database_rejects_incompatible_presentation_graph_and_preserves_configuration(string $case): void
    {
        $p = app(ConfigureCostingPolicy::class)->execute(null, $this->policyPayload());
        $header = DB::table('inventory_costing_policy_presentations')->where('commercial_quantity', 6)->sole();
        $c = DB::table('inventory_costing_policy_packaging_components')->where('presentation_id', $header->id)->sole();
        $before = DB::table('inventory_costing_policy_packaging_components')->orderBy('id')->get()->toJson();
        try {
            DB::transaction(function () use ($case, $header, $c): void {
                match ($case) {
                    'empty_components' => DB::table('inventory_costing_policy_packaging_components')->where('id', $c->id)->delete(),
                    'none_with_components' => DB::table('inventory_costing_policy_presentations')->where('id', $header->id)->update(['packaging_mode' => 'none']),
                    'invalid_mode' => DB::table('inventory_costing_policy_presentations')->where('id', $header->id)->update(['packaging_mode' => 'guess']),
                    'nan' => DB::table('inventory_costing_policy_packaging_components')->where('id', $c->id)->update(['quantity' => 'NaN']),
                    'wrong_unit' => DB::table('inventory_costing_policy_packaging_components')->where('id', $c->id)->update(['unit_id' => $this->gram->id]),
                    'wrong_kind' => DB::table('inventory_costing_policy_packaging_components')->where('id', $c->id)->update(['inventory_item_id' => $this->filling->id]),
                    'duplicate_presentation' => DB::table('inventory_costing_policy_presentations')->insert(['costing_policy_id' => $header->costing_policy_id, 'commercial_quantity' => 6, 'packaging_mode' => 'none']),
                };
                DB::statement('SET CONSTRAINTS ALL IMMEDIATE');
            });
            $this->fail('Invalid configuration was committed');
        } catch (QueryException $e) {
            $this->assertContains($e->errorInfo[0], ['23514', '23505']);
            $this->assertSame($before, DB::table('inventory_costing_policy_packaging_components')->orderBy('id')->get()->toJson());
            $this->assertSame('components', DB::table('inventory_costing_policy_presentations')->where('id', $header->id)->value('packaging_mode'));
        }
    }

    public static function invalidSql(): array
    {
        return array_map(fn ($v) => [$v], ['empty_components', 'none_with_components', 'invalid_mode', 'nan', 'wrong_unit', 'wrong_kind', 'duplicate_presentation']);
    }

    public function test_populated_migration_rollback_refuses_without_removing_policy_or_assignment(): void
    {
        $p = app(ConfigureCostingPolicy::class)->execute(null, $this->policyPayload());
        app(AssignProductCostingPolicy::class)->execute($this->product->id, $p->id);
        $migration = require database_path('migrations/2026_10_13_000000_create_current_product_costing.php');
        try {
            $migration->down();
            $this->fail('Populated rollback allowed');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('Refusing populated', $e->getMessage());
        }
        $this->assertDatabaseCount('inventory_costing_policies', 1);
        $this->assertDatabaseCount('inventory_product_costing_policies', 1);
        $this->assertDatabaseCount('inventory_costing_policy_packaging_components', 2);
        DB::statement('SET CONSTRAINTS ALL IMMEDIATE');
    }

    public function test_empty_incremental_migration_down_up_preserves_existing_units_and_history_schema(): void
    {
        $before = DB::table('inventory_units')->orderBy('id')->get()->toJson();
        $migration = require database_path('migrations/2026_10_13_000000_create_current_product_costing.php');
        $migration->down();
        $this->assertFalse(Schema::hasTable('inventory_costing_policies'));
        $this->assertTrue(Schema::hasTable('order_inventory_consumptions'));
        $migration->up();
        $this->assertSame($before, DB::table('inventory_units')->orderBy('id')->get()->toJson());
        $this->assertDatabaseCount('inventory_costing_policies', 0);
    }
}
