<?php

namespace Tests\Feature\Inventory;

use App\Modules\Identity\Models\User;
use App\Modules\Inventory\Actions\CreateProductionBatch;
use App\Modules\Inventory\Actions\CreateRecipe;
use App\Modules\Inventory\Actions\CreateRecipeVersion;
use App\Modules\Inventory\Actions\RecordInventoryTransaction;
use App\Modules\Inventory\Actions\ReverseProductionBatch;
use App\Modules\Inventory\Data\InventoryMovementData;
use App\Modules\Inventory\Data\InventoryTransactionData;
use App\Modules\Inventory\Data\ProductionBatchData;
use App\Modules\Inventory\Data\RecipeIngredientData;
use App\Modules\Inventory\Data\RecipeVersionData;
use App\Modules\Inventory\Enums\InventorySourceType;
use App\Modules\Inventory\Enums\InventoryTransactionType;
use App\Modules\Inventory\Models\InventoryCategory;
use App\Modules\Inventory\Models\InventoryItem;
use App\Modules\Inventory\Models\InventoryUnit;
use App\Modules\Inventory\Models\ProductionBatch;
use App\Modules\Inventory\Models\Recipe;
use App\Modules\Inventory\Models\RecipeVersion;
use App\Modules\Inventory\Queries\BuildRecipeCurrentCost;
use App\Modules\Inventory\Queries\InventoryValuationReadModel;
use App\Modules\Inventory\Support\DecimalMath;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

final class RecipeProductionRecoveryTest extends TestCase
{
    use RefreshDatabase;

    public function test_referenced_item_edits_report_validation_before_database_rejection(): void
    {
        [$admin, $onion, $beef, $output] = $this->fixture();
        $values = ['category_id' => $output->inventory_category_id, 'name' => $output->name,
            'tracks_stock' => true, 'unit_family' => 'weight', 'base_unit_id' => $output->base_unit_id,
            'default_input_unit_id' => $output->default_input_unit_id, 'quantity_precision' => $output->quantity_precision,
            'is_active' => true, 'kind' => 'semi_finished', 'recipe_input_enabled' => $output->recipe_input_enabled,
            'production_output_enabled' => true, 'order_consumption_enabled' => $output->order_consumption_enabled];
        $this->actingAs($admin)->put('/admin/inventory/items/'.$output->id, array_replace($values, ['quantity_precision' => 2]))->assertSessionHasErrors('measurement');
        $this->put('/admin/inventory/items/'.$output->id, array_replace($values, ['production_output_enabled' => false]))->assertSessionHasErrors('production_output_enabled');
    }

    public function test_duplicate_recipe_name_is_a_validation_error_not_a_database_failure(): void
    {
        $admin = User::factory()->administrator()->create();
        app(CreateRecipe::class)->execute('Relleno Criolla Dulce', $admin->id);
        $this->expectException(ValidationException::class);
        app(CreateRecipe::class)->execute('  relleno criolla dulce  ', $admin->id);
    }

    public function test_explicit_output_exact_ingredients_and_new_version_preserve_old_formula(): void
    {
        [$admin, $onion, $beef, $output, $recipe, $version] = $this->fixture();
        $this->assertSame('2.000000', $version->expected_output_quantity);
        $this->assertSame('2000.000000', $version->expected_output_base_quantity);
        $this->assertSame(['1000.000000', '1000.000000'], $version->ingredients->pluck('base_quantity')->all());
        $next = app(CreateRecipeVersion::class)->execute($recipe->id, new RecipeVersionData($output->id, $this->unit('kg')->id, '1.5', [new RecipeIngredientData($onion->id, $this->unit('g')->id, '150')]), $admin->id);
        $this->assertFalse($version->fresh()->is_active);
        $this->assertTrue($next->is_active);
        $this->assertSame(2, $next->version);
        $this->assertSame('1500.000000', $next->expected_output_base_quantity);
        $this->assertSame('2000.000000', $version->fresh()->expected_output_base_quantity);
        $this->assertSame(2, $version->ingredients()->count());
    }

    public function test_output_input_capabilities_duplicates_and_invalid_quantities_are_rejected(): void
    {
        [$admin, $onion, $beef, $output, $recipe] = $this->fixture();
        $invalid = $this->item('Not eligible', false, false);
        foreach ([
            new RecipeVersionData($invalid->id, $this->unit('kg')->id, '2', [new RecipeIngredientData($onion->id, $this->unit('kg')->id, '1')]),
            new RecipeVersionData($output->id, $this->unit('kg')->id, '2', [new RecipeIngredientData($invalid->id, $this->unit('kg')->id, '1')]),
            new RecipeVersionData($output->id, $this->unit('kg')->id, '0', [new RecipeIngredientData($onion->id, $this->unit('kg')->id, '1')]),
            new RecipeVersionData($output->id, $this->unit('kg')->id, '2', [new RecipeIngredientData($onion->id, $this->unit('kg')->id, '0')]),
            new RecipeVersionData($output->id, $this->unit('kg')->id, '2', []),
            new RecipeVersionData($output->id, $this->unit('kg')->id, '2', [new RecipeIngredientData($onion->id, $this->unit('kg')->id, '1'), new RecipeIngredientData($onion->id, $this->unit('kg')->id, '1')]),
            new RecipeVersionData($output->id, $this->unit('kg')->id, '2', [new RecipeIngredientData($output->id, $this->unit('kg')->id, '1')]),
        ] as $data) {
            try {
                app(CreateRecipeVersion::class)->execute($recipe->id, $data, $admin->id);
                $this->fail('Expected invalid formula');
            } catch (ValidationException) {
                $this->assertSame(1, $recipe->versions()->count());
            }
        }
    }

    public function test_current_cost_uses_integer_pesos_and_explicit_output(): void
    {
        [$admin, $onion, $beef, $output, $recipe, $version] = $this->fixture();
        $cost = app(BuildRecipeCurrentCost::class)->build($version);
        $this->assertSame('complete', $cost['status']);
        $this->assertSame(16650, $cost['estimated_total_cost']);
        $this->assertSame(8325, $cost['estimated_cost_per_output_unit']);
        $this->assertSame(10000, $cost['coverage']['percentage_bps']);
    }

    public function test_unknown_consumption_does_not_hide_later_negative_physical_stock(): void
    {
        [$admin, $onion, $beef, $output, $recipe] = $this->fixture();
        app(RecordInventoryTransaction::class)->execute(new InventoryTransactionData(InventoryTransactionType::Adjustment, CarbonImmutable::parse('2026-10-07'), [new InventoryMovementData($onion->id, '1000', null)], InventorySourceType::Adjustment, (string) Str::uuid()), $admin->id);
        $v = app(CreateRecipeVersion::class)->execute($recipe->id, new RecipeVersionData($output->id, $this->unit('kg')->id, '2', [new RecipeIngredientData($onion->id, $this->unit('kg')->id, '2')]), $admin->id);
        $this->assertNull($this->produce($admin, $v)->total_cost);
        $next = app(CreateRecipeVersion::class)->execute($recipe->id, new RecipeVersionData($output->id, $this->unit('kg')->id, '1', [new RecipeIngredientData($onion->id, $this->unit('kg')->id, '1')]), $admin->id);
        $batch = $this->produce($admin, $next);
        $this->assertSame(2150, $batch->total_cost);
        $this->assertSame('unreliable', $batch->cost_coverage);
        $this->assertSame('insufficient_stock', $batch->allocations->sole()->fallback);
        DB::statement('SET CONSTRAINTS ALL IMMEDIATE');
    }

    public function test_unknown_current_cost_is_not_zero(): void
    {
        [$admin, $onion, $beef, $output, $recipe] = $this->fixture();
        $unknown = $this->item('No cost history');
        $v = app(CreateRecipeVersion::class)->execute($recipe->id, new RecipeVersionData($output->id, $this->unit('kg')->id, '2', [new RecipeIngredientData($onion->id, $this->unit('kg')->id, '1'), new RecipeIngredientData($unknown->id, $this->unit('kg')->id, '1')]), $admin->id);
        $cost = app(BuildRecipeCurrentCost::class)->build($v);
        $this->assertSame('partial', $cost['status']);
        $this->assertSame(2150, $cost['known_cost']);
        $this->assertNull($cost['estimated_total_cost']);
        $this->assertNull($cost['ingredients'][1]['estimated_cost']);
        $this->assertSame(5000, $cost['coverage']['percentage_bps']);
    }

    public function test_actual_production_transfers_total_cost_and_reversal_ignores_current_prices(): void
    {
        [$admin, $onion, $beef, $output, $recipe, $v] = $this->fixture();
        $data = new ProductionBatchData($v->id, $this->unit('kg')->id, '1.85', CarbonImmutable::parse('2026-10-07'), 'a31a4d05-7d9e-4e38-920b-048a3ce81853');
        $b = app(CreateProductionBatch::class)->execute($data, $admin->id);
        $this->assertSame('1850.000000', $b->actual_output_base_quantity);
        $this->assertSame('2000.000000', $b->expected_output_base_quantity);
        $this->assertSame(16650, $b->total_cost);
        $this->assertSame('complete', $b->cost_coverage);
        $this->assertSame('9000.000000000', DecimalMath::divideIntegerByDecimal($b->total_cost, $b->actual_output_quantity, 9));
        $this->assertSame('-1000.000000', $b->transaction->movements->firstWhere('inventory_item_id', $onion->id)->quantity);
        $this->assertSame('1850.000000', $this->stock($output));
        $this->assertSame($b->id, app(CreateProductionBatch::class)->execute($data, $admin->id)->id);
        $this->receipt($admin, $beef, '1000', 18000);
        $this->assertSame(16650, $b->fresh()->total_cost);
        $r = app(ReverseProductionBatch::class)->execute($b->id, CarbonImmutable::parse('2026-10-08'), 'Duplicate production', $admin->id);
        $this->assertSame('0.000000', $this->stock($output));
        foreach ($b->transaction->movements as $m) {
            $opposite = $r->inventoryTransaction->movements()->where('inventory_item_id', $m->inventory_item_id)->sole();
            $this->assertSame(DecimalMath::subtract('0', $m->quantity), $opposite->quantity);
            $this->assertSame(-$m->costLayer->total_cost, $opposite->costLayer->total_cost);
            $this->assertSame($m->costLayer->id, $opposite->costLayer->reversal_of_id);
        }
        $this->expectException(ValidationException::class);
        app(ReverseProductionBatch::class)->execute($b->id, CarbonImmutable::parse('2026-10-08'), 'Again', $admin->id);
    }

    public function test_negative_stock_fallback_is_historical_and_current_cost_unreliable(): void
    {
        [$admin, $onion, $beef, $output, $recipe, $v] = $this->fixture();
        $this->produce($admin, $v);
        $b = $this->produce($admin, $v);
        $this->assertSame(16650, $b->total_cost);
        $this->assertSame('unreliable', $b->cost_coverage);
        $this->assertSame('negative_stock_latest_basis', $b->operational_warnings[0]['code']);
        $this->assertSame('-1000.000000', $this->stock($onion));
        $this->assertSame('unreliable', app(BuildRecipeCurrentCost::class)->build($v)['status']);
    }

    public function test_unknown_production_cost_does_not_create_a_known_zero_output_layer(): void
    {
        [$admin, $onion, $beef, $output, $recipe] = $this->fixture();
        $u = $this->item('Unknown input');
        $v = app(CreateRecipeVersion::class)->execute($recipe->id, new RecipeVersionData($output->id, $this->unit('kg')->id, '1', [new RecipeIngredientData($u->id, $this->unit('kg')->id, '1')]), $admin->id);
        $b = $this->produce($admin, $v);
        $this->assertNull($b->total_cost);
        $this->assertSame('unknown', $b->cost_coverage);
        $this->assertSame(0, $b->transaction->costLayers()->count());
        $this->assertNull($b->allocations->sole()->allocated_cost);
        app(ReverseProductionBatch::class)->execute($b->id, CarbonImmutable::parse('2026-10-08'), 'Unknown cost reversal', $admin->id);
        $this->assertSame('0.000000', $this->stock($u));
        $this->assertSame('0.000000', $this->stock($output));
    }

    public function test_reference_mass_is_auxiliary_and_count_stock_stays_in_units(): void
    {
        [$admin, $onion, $beef, $output, $recipe] = $this->fixture();
        $egg = $this->item('Egg');
        $egg->update(['unit_family' => 'count', 'base_unit_id' => $this->unit('unit')->id, 'default_input_unit_id' => $this->unit('unit')->id, 'quantity_precision' => 0, 'reference_mass_grams_per_unit' => '60']);
        $v = app(CreateRecipeVersion::class)->execute($recipe->id, new RecipeVersionData($output->id, $this->unit('kg')->id, '1', [new RecipeIngredientData($egg->id, $this->unit('unit')->id, '6')]), $admin->id);
        $this->assertSame('6.000000', $v->ingredients->sole()->base_quantity);
        $this->produce($admin, $v);
        $this->assertSame('-6.000000', $this->stock($egg));
    }

    public function test_database_sealed_ingredients_cannot_be_mutated_or_appended(): void
    {
        [$admin, $onion, $beef, $output, $recipe, $v] = $this->fixture();
        $this->expectException(QueryException::class);
        DB::table('inventory_recipe_ingredients')->where('recipe_version_id', $v->id)->update(['quantity' => '3']);
    }

    public function test_database_recipe_measurement_is_protected_before_any_movements(): void
    {
        [$admin, $onion, $beef, $output, $recipe, $v] = $this->fixture();
        $this->expectException(QueryException::class);
        $output->update(['quantity_precision' => 0]);
    }

    public function test_retired_version_cannot_be_used_for_new_production(): void
    {
        [$admin, $onion, $beef, $output, $recipe, $v] = $this->fixture();
        app(CreateRecipeVersion::class)->execute($recipe->id, new RecipeVersionData($output->id, $this->unit('kg')->id, '3', [new RecipeIngredientData($onion->id, $this->unit('kg')->id, '1')]), $admin->id);
        $this->expectException(ValidationException::class);
        $this->produce($admin, $v);
    }

    public function test_administrator_http_create_requires_output_and_expected_quantity(): void
    {
        $admin = User::factory()->administrator()->create();
        $this->actingAs($admin)->post('/admin/inventory/recipes', ['name' => 'Missing output'])->assertSessionHasErrors(['output_inventory_item_id', 'expected_output_quantity']);
        $this->assertDatabaseCount('inventory_recipes', 0);
    }

    public function test_current_and_retired_are_filtered_before_pagination(): void
    {
        [$admin,$onion,$beef,$output,$recipe,$first] = $this->fixture();
        for ($n = 0; $n < 11; $n++) {
            app(CreateRecipeVersion::class)->execute($recipe->id, new RecipeVersionData($output->id, $this->unit('kg')->id, '2', [new RecipeIngredientData($onion->id, $this->unit('kg')->id, '1')]), $admin->id);
        }
        $this->actingAs($admin)->get('/admin/inventory/recipes/'.$recipe->id)->assertInertia(fn (AssertableInertia $p) => $p->component('admin/inventory/recipes/show')->where('version_view', 'current')->where('versions.total', 1)->has('versions.data', 1));
        $this->get('/admin/inventory/recipes/'.$recipe->id.'?view=retired')->assertInertia(fn (AssertableInertia $p) => $p->where('versions.total', 11)->has('versions.data', 10)->where('version_view', 'retired'));
        $this->get('/admin/inventory/recipes/'.$recipe->id.'?view=retired&page=2')->assertInertia(fn (AssertableInertia $p) => $p->where('versions.total', 11)->has('versions.data', 1));
        $this->get('/admin/inventory/recipes/'.$recipe->id.'?view=invalid')->assertInertia(fn (AssertableInertia $p) => $p->where('version_view', 'current')->where('versions.total', 1));
    }

    public function test_waiter_and_cashier_cannot_read_or_write_recipes_or_production(): void
    {
        [$admin,$onion,$beef,$output,$recipe,$v] = $this->fixture();
        $b = $this->produce($admin, $v);
        foreach ([User::factory()->create(['staff_profile' => 'waiter']), User::factory()->create(['staff_profile' => 'cashier'])] as $staff) {
            $this->actingAs($staff);
            foreach (['/admin/inventory/recipes', '/admin/inventory/recipes/create', '/admin/inventory/recipes/'.$recipe->id, '/admin/inventory/production', '/admin/inventory/production/create', '/admin/inventory/production/'.$b->id] as $path) {
                $this->get($path)->assertForbidden();
            }
            foreach (['/admin/inventory/recipes', '/admin/inventory/recipes/'.$recipe->id.'/versions', '/admin/inventory/production', '/admin/inventory/production/'.$b->id.'/reverse'] as $path) {
                $this->post($path, [])->assertForbidden();
            }
        }
    }

    public function test_production_http_creates_displays_and_reverses_exact_batch(): void
    {
        [$admin,$onion,$beef,$output,$recipe,$v] = $this->fixture();
        $this->actingAs($admin)->get('/admin/inventory/production')->assertInertia(fn (AssertableInertia $p) => $p->component('admin/inventory/production/index')->has('batches.data', 0));
        $this->post('/admin/inventory/production', ['recipe_version_id' => $v->id, 'output_unit_id' => $this->unit('kg')->id, 'actual_output_quantity' => '1.85', 'effective_date' => '2026-10-07', 'idempotency_key' => 'A31A4D05-7D9E-4E38-920B-048A3CE81853'])->assertRedirect();
        $b = ProductionBatch::query()->sole();
        $this->get('/admin/inventory/production/'.$b->id)->assertInertia(fn (AssertableInertia $p) => $p->component('admin/inventory/production/show')->where('batch.total_cost', 16650)->where('batch.historical_cost_per_output_unit', 9000)->where('batch.cost_coverage', 'complete'));
        $this->post('/admin/inventory/production/'.$b->id.'/reverse', ['effective_date' => '2026-10-08', 'reason' => 'Duplicate'])->assertRedirect();
        $this->assertSame('0.000000', $this->stock($output));
        DB::statement('SET CONSTRAINTS ALL IMMEDIATE');
    }

    public function test_unreliable_produced_item_is_not_silently_counted_as_confident_inventory(): void
    {
        [$admin,$onion,$beef,$output,$recipe,$v] = $this->fixture();
        $this->produce($admin, $v);
        $this->produce($admin, $v);
        $output->setAttribute('stock_quantity', '3700.000000');
        $output->load('category');
        $result = app(InventoryValuationReadModel::class)->summarize(new Collection([$output]));
        $this->assertSame('unreliable', $result['by_item'][$output->id]['coverage']);
        $this->assertNull($result['by_item'][$output->id]['value']);
        $this->assertSame(0, $result['summary']['confident_value']);
    }

    public function test_prices_change_current_estimate_but_not_retired_formula_or_production(): void
    {
        [$admin,$onion,$beef,$output,$recipe,$v] = $this->fixture();
        $b = $this->produce($admin, $v);
        $this->receipt($admin, $onion, '1000', 2150);
        $this->receipt($admin, $beef, '1000', 18000);
        app(CreateRecipeVersion::class)->execute($recipe->id, new RecipeVersionData($output->id, $this->unit('kg')->id, '3', [new RecipeIngredientData($onion->id, $this->unit('kg')->id, '1')]), $admin->id);
        $this->assertSame(20150, app(BuildRecipeCurrentCost::class)->build($v->fresh())['estimated_total_cost']);
        $this->assertSame(16650, $b->fresh()->total_cost);
        $this->assertSame('2000.000000', $v->fresh()->expected_output_base_quantity);
    }

    public function test_known_free_input_is_distinct_from_unknown_cost(): void
    {
        [$admin,$onion,$beef,$output,$recipe] = $this->fixture();
        $free = $this->item('Known free input');
        $this->receipt($admin, $free, '1000', 0);
        $v = app(CreateRecipeVersion::class)->execute($recipe->id, new RecipeVersionData($output->id, $this->unit('kg')->id, '1', [new RecipeIngredientData($free->id, $this->unit('kg')->id, '1')]), $admin->id);
        $this->assertSame(0, app(BuildRecipeCurrentCost::class)->build($v)['estimated_total_cost']);
        $b = $this->produce($admin, $v);
        $this->assertSame(0, $b->total_cost);
        $this->assertSame('complete', $b->cost_coverage);
    }

    public function test_partial_inventory_valuation_never_claims_complete_recipe_or_production_cost(): void
    {
        [$admin,$onion,$beef,$output,$recipe,$v] = $this->fixture();
        app(RecordInventoryTransaction::class)->execute(new InventoryTransactionData(InventoryTransactionType::Adjustment, CarbonImmutable::parse('2026-10-02'), [new InventoryMovementData($onion->id, '1000')], InventorySourceType::Adjustment, (string) Str::uuid()), $admin->id);
        $this->assertSame('unreliable', app(BuildRecipeCurrentCost::class)->build($v)['status']);
        $b = $this->produce($admin, $v);
        $this->assertNull($b->total_cost);
        $this->assertSame('partial', $b->cost_coverage);
        $this->assertSame('partial_valuation', $b->operational_warnings[0]['code']);
    }

    public function test_actual_output_zero_is_rejected_without_any_batch_or_consumption(): void
    {
        [$admin,$onion,$beef,$output,$recipe,$v] = $this->fixture();
        try {
            app(CreateProductionBatch::class)->execute(new ProductionBatchData($v->id, $this->unit('kg')->id, '0', CarbonImmutable::parse('2026-10-07'), (string) Str::uuid()), $admin->id);
            $this->fail('Expected invalid actual output');
        } catch (ValidationException) {
            $this->assertDatabaseCount('inventory_production_batches', 0);
            $this->assertSame('1000.000000', $this->stock($onion));
        }
    }

    /** @return array{User,InventoryItem,InventoryItem,InventoryItem,Recipe,RecipeVersion} */
    private function fixture(): array
    {
        $admin = User::factory()->administrator()->create();
        $onion = $this->item('Cebolla');
        $beef = $this->item('Roast Beef');
        $output = $this->item('Relleno Criolla Dulce', true, true);
        $this->receipt($admin, $onion, '1000', 2150);
        $this->receipt($admin, $beef, '1000', 14500);
        $recipe = app(CreateRecipe::class)->execute('Relleno Criolla Dulce', $admin->id);
        $v = app(CreateRecipeVersion::class)->execute($recipe->id, new RecipeVersionData($output->id, $this->unit('kg')->id, '2', [new RecipeIngredientData($onion->id, $this->unit('kg')->id, '1'), new RecipeIngredientData($beef->id, $this->unit('kg')->id, '1')]), $admin->id);

        return [$admin, $onion, $beef, $output, $recipe, $v];
    }

    private function item(string $name, bool $input = true, bool $output = false): InventoryItem
    {
        $c = InventoryCategory::query()->firstOrCreate(['name' => 'Recipe ingredients']);

        return InventoryItem::query()->create(['inventory_category_id' => $c->id, 'name' => $name, 'tracks_stock' => true, 'unit_family' => 'weight', 'base_unit_id' => $this->unit('g')->id, 'default_input_unit_id' => $this->unit('kg')->id, 'quantity_precision' => 3, 'is_active' => true, 'kind' => $output ? 'semi_finished' : 'raw_material', 'recipe_input_enabled' => $input, 'production_output_enabled' => $output, 'order_consumption_enabled' => false]);
    }

    private function unit(string $code): InventoryUnit
    {
        return InventoryUnit::query()->where('code', $code)->sole();
    }

    private function receipt(User $admin, InventoryItem $item, string $q, int $cost): void
    {
        app(RecordInventoryTransaction::class)->execute(new InventoryTransactionData(InventoryTransactionType::Adjustment, CarbonImmutable::parse('2026-10-01'), [new InventoryMovementData($item->id, $q, $cost)], InventorySourceType::Adjustment, (string) Str::uuid()), $admin->id);
    }

    private function produce(User $admin, RecipeVersion $v): ProductionBatch
    {
        return app(CreateProductionBatch::class)->execute(new ProductionBatchData($v->id, $this->unit('kg')->id, '1.85', CarbonImmutable::parse('2026-10-07'), (string) Str::uuid()), $admin->id);
    }

    private function stock(InventoryItem $i): string
    {
        return DecimalMath::normalize((string) $i->stockMovements()->sum('quantity'), 6);
    }
}
