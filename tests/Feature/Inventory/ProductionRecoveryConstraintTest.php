<?php

namespace Tests\Feature\Inventory;

use App\Modules\Identity\Models\User;
use App\Modules\Inventory\Actions\CreateProductionBatch;
use App\Modules\Inventory\Actions\CreateRecipe;
use App\Modules\Inventory\Actions\CreateRecipeVersion;
use App\Modules\Inventory\Actions\RecordInventoryTransaction;
use App\Modules\Inventory\Data\InventoryMovementData;
use App\Modules\Inventory\Data\InventoryTransactionData;
use App\Modules\Inventory\Data\ProductionBatchData;
use App\Modules\Inventory\Data\RecipeIngredientData;
use App\Modules\Inventory\Data\RecipeVersionData;
use App\Modules\Inventory\Enums\InventorySourceType;
use App\Modules\Inventory\Enums\InventoryTransactionType;
use App\Modules\Inventory\Models\InventoryCategory;
use App\Modules\Inventory\Models\InventoryItem;
use App\Modules\Inventory\Models\InventoryTransaction;
use App\Modules\Inventory\Models\InventoryUnit;
use App\Modules\Inventory\Models\ProductionBatch;
use App\Modules\Inventory\Models\ProductionCostAllocation;
use App\Modules\Inventory\Models\RecipeVersion;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

final class ProductionRecoveryConstraintTest extends TestCase
{
    use RefreshDatabase;

    public function test_database_rejects_retired_formula_at_batch_insert(): void
    {
        [$admin,$input,$output,$v,$unit] = $this->fixture();
        $v->update(['is_active' => false]);
        $this->rejected(function () use ($admin, $output, $v, $unit): void {
            ProductionBatch::query()->create(['recipe_version_id' => $v->id, 'output_inventory_item_id' => $output->id, 'expected_output_base_quantity' => '2', 'actual_output_quantity' => '2', 'actual_output_base_quantity' => '2', 'output_unit_id' => $unit->id, 'effective_date' => '2026-10-07', 'created_by' => $admin->id, 'idempotency_key' => (string) Str::uuid(), 'fingerprint' => str_repeat('a', 64)]);
            DB::statement('SET CONSTRAINTS ALL IMMEDIATE');
        }, '23514', 'current recipe');
    }

    public function test_database_rejects_forged_allocator_basis_even_when_total_conserves(): void
    {
        [$admin,$input,$output,$v,$unit] = $this->fixture();
        $this->rejected(function () use ($admin, $input, $output, $v, $unit): void {
            $b = ProductionBatch::query()->create(['recipe_version_id' => $v->id, 'output_inventory_item_id' => $output->id, 'expected_output_base_quantity' => '2', 'actual_output_quantity' => '2', 'actual_output_base_quantity' => '2', 'output_unit_id' => $unit->id, 'effective_date' => '2026-10-07', 'created_by' => $admin->id, 'idempotency_key' => (string) Str::uuid(), 'fingerprint' => str_repeat('b', 64)]);
            $t = InventoryTransaction::query()->create(['type' => 'production', 'effective_date' => '2026-10-07', 'source_type' => 'production', 'source_id' => (string) $b->id, 'created_by' => $admin->id]);
            $m = $t->movements()->create(['inventory_item_id' => $input->id, 'quantity' => '-1', 'unit_id' => $unit->id]);
            $l = $t->costLayers()->create(['inventory_item_id' => $input->id, 'stock_movement_id' => $m->id, 'quantity' => '-1', 'total_cost' => -7, 'effective_date' => '2026-10-07']);
            ProductionCostAllocation::query()->create(['production_batch_id' => $b->id, 'stock_movement_id' => $m->id, 'inventory_cost_layer_id' => $l->id, 'basis_quantity' => '1', 'basis_total_cost' => 7, 'allocated_quantity' => '1', 'allocated_cost' => 7, 'method' => 'moving_weighted_average']);
            $m = $t->movements()->create(['inventory_item_id' => $output->id, 'quantity' => '2', 'unit_id' => $unit->id]);
            $t->costLayers()->create(['inventory_item_id' => $output->id, 'stock_movement_id' => $m->id, 'quantity' => '2', 'total_cost' => 7, 'effective_date' => '2026-10-07']);
            $b->update(['inventory_transaction_id' => $t->id, 'total_cost' => 7, 'cost_coverage' => 'complete']);
            DB::statement('SET CONSTRAINTS ALL IMMEDIATE');
        }, '23514', 'allocator basis');
    }

    public function test_database_requires_negative_fallback_and_unreliable_output_provenance(): void
    {
        [$admin, $input, $output, $v, $unit] = $this->fixture();
        app(CreateProductionBatch::class)->execute(
            new ProductionBatchData($v->id, $unit->id, '2', CarbonImmutable::parse('2026-10-07'), (string) Str::uuid()), $admin->id);
        foreach ([null, 'negative_stock_latest_basis'] as $fallback) {
            $this->rejected(function () use ($admin, $input, $output, $v, $unit, $fallback): void {
                $b = ProductionBatch::query()->create(['recipe_version_id' => $v->id, 'output_inventory_item_id' => $output->id, 'expected_output_base_quantity' => '2', 'actual_output_quantity' => '2', 'actual_output_base_quantity' => '2', 'output_unit_id' => $unit->id, 'effective_date' => '2026-10-07', 'created_by' => $admin->id, 'idempotency_key' => (string) Str::uuid(), 'fingerprint' => str_repeat('c', 64)]);
                $t = InventoryTransaction::query()->create(['type' => 'production', 'effective_date' => '2026-10-07', 'source_type' => 'production', 'source_id' => (string) $b->id, 'created_by' => $admin->id]);
                $m = $t->movements()->create(['inventory_item_id' => $input->id, 'quantity' => '-1', 'unit_id' => $unit->id]);
                $l = $t->costLayers()->create(['inventory_item_id' => $input->id, 'stock_movement_id' => $m->id, 'quantity' => '-1', 'total_cost' => -100, 'effective_date' => '2026-10-07', 'valuation_warning' => $fallback]);
                ProductionCostAllocation::query()->create(['production_batch_id' => $b->id, 'stock_movement_id' => $m->id, 'inventory_cost_layer_id' => $l->id, 'basis_quantity' => '1', 'basis_total_cost' => 100, 'allocated_quantity' => '1', 'allocated_cost' => 100, 'method' => 'moving_weighted_average', 'fallback' => $fallback]);
                $m = $t->movements()->create(['inventory_item_id' => $output->id, 'quantity' => '2', 'unit_id' => $unit->id]);
                $t->costLayers()->create(['inventory_item_id' => $output->id, 'stock_movement_id' => $m->id, 'quantity' => '2', 'total_cost' => 100, 'effective_date' => '2026-10-07']);
                $b->update(['inventory_transaction_id' => $t->id, 'total_cost' => 100, 'cost_coverage' => 'complete']);
                DB::statement('SET CONSTRAINTS ALL IMMEDIATE');
            }, '23514', $fallback === null ? 'fallback' : 'warning');
        }
    }

    public function test_populated_aggregate_rollback_refuses_without_deleting_history(): void
    {
        $this->fixture();
        $migration = require database_path('migrations/2026_10_07_000000_create_inventory_recipes_and_production.php');
        try {
            $migration->down();
            $this->fail('Populated aggregate rollback must refuse.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('empty aggregate boundary', $e->getMessage());
            $this->assertSame(1, RecipeVersion::query()->count());
            $this->assertSame(1, DB::table('inventory_recipe_ingredients')->count());
        }
    }

    public function test_database_rejects_active_capability_removal(): void
    {
        [$admin,$input] = $this->fixture();
        $this->rejected(fn () => $input->update(['recipe_input_enabled' => false]), '23514', 'capabilities');
    }

    public function test_database_rejects_recipe_history_delete_and_incorrect_expected_conversion(): void
    {
        [$admin,$input,$output,$v,$unit] = $this->fixture();
        // An unused formula may be deleted only together with its ingredients (see RecipeLifecycleTest).
        $this->rejected(fn () => $v->delete(), '23001', 'inventory_recipe_ingredients');
        $this->rejected(fn () => DB::table('inventory_recipe_versions')->insert(['recipe_id' => $v->recipe_id, 'version' => 2, 'recipe_name_snapshot' => $v->recipe_name_snapshot, 'output_inventory_item_id' => $output->id, 'output_unit_id' => $unit->id, 'expected_output_quantity' => 1, 'expected_output_base_quantity' => 2, 'created_by' => $admin->id]), '23514', 'conversion');
    }

    public function test_orphan_production_transaction_cannot_commit(): void
    {
        [$admin,$input,$output,$v,$unit] = $this->fixture();
        $this->rejected(function () use ($admin, $input, $unit): void {
            $t = InventoryTransaction::query()->create(['type' => 'production', 'effective_date' => '2026-10-07', 'source_type' => 'production', 'source_id' => '999', 'created_by' => $admin->id]);
            $t->movements()->create(['inventory_item_id' => $input->id, 'quantity' => 1, 'unit_id' => $unit->id]);
            DB::statement('SET CONSTRAINTS ALL IMMEDIATE');
        }, '23514', 'batch source');
    }

    private function rejected(callable $work, string $state, string $message): void
    {
        try {
            DB::transaction($work);
            $this->fail('Expected database rejection: '.$message);
        } catch (QueryException $e) {
            $this->assertSame($state, $e->errorInfo[0]);
            $this->assertStringContainsString($message, $e->getMessage());
        }
    }

    private function fixture(): array
    {
        $a = User::factory()->administrator()->create();
        $u = InventoryUnit::query()->where('code', 'g')->sole();
        $c = InventoryCategory::query()->create(['name' => 'Production constraints']);
        $attrs = ['inventory_category_id' => $c->id, 'tracks_stock' => true, 'is_active' => true, 'unit_family' => 'weight', 'base_unit_id' => $u->id, 'default_input_unit_id' => $u->id, 'quantity_precision' => 3, 'recipe_input_enabled' => true, 'production_output_enabled' => true, 'kind' => 'semi_finished'];
        $i = InventoryItem::query()->create($attrs + ['name' => 'Input']);
        $o = InventoryItem::query()->create($attrs + ['name' => 'Output']);
        app(RecordInventoryTransaction::class)->execute(new InventoryTransactionData(InventoryTransactionType::Adjustment, CarbonImmutable::parse('2026-10-01'), [new InventoryMovementData($i->id, '1', 100)], InventorySourceType::Adjustment, (string) Str::uuid()), $a->id);
        $r = app(CreateRecipe::class)->execute('Recipe', $a->id);
        $v = app(CreateRecipeVersion::class)->execute($r->id, new RecipeVersionData($o->id, $u->id, '2', [new RecipeIngredientData($i->id, $u->id, '1')]), $a->id);

        return [$a, $i, $o, $v, $u];
    }
}
