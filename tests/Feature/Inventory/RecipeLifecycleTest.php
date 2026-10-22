<?php

namespace Tests\Feature\Inventory;

use App\Modules\Identity\Enums\Role;
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
use App\Modules\Inventory\Models\InventoryUnit;
use App\Modules\Inventory\Models\ProductionBatch;
use App\Modules\Inventory\Models\Recipe;
use App\Modules\Inventory\Models\RecipeVersion;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

final class RecipeLifecycleTest extends TestCase
{
    use RefreshDatabase;

    public function test_retiring_a_recipe_keeps_its_history_and_blocks_new_use(): void
    {
        [$admin, $onion, , $output, $recipe, $version] = $this->fixture();

        $this->actingAs($admin)->post('/admin/inventory/recipes/'.$recipe->id.'/retire')
            ->assertRedirect('/admin/inventory/recipes/'.$recipe->id)
            ->assertSessionHasNoErrors();

        $this->assertFalse($recipe->fresh()->is_active);
        $this->assertFalse($version->fresh()->is_active);
        $this->assertTrue($version->fresh()->is_sealed);
        $this->assertSame(2, $version->ingredients()->count());
        $this->get('/admin/inventory/recipes/'.$recipe->id)->assertInertia(fn (AssertableInertia $p) => $p->where('recipe.is_active', false)->where('versions.total', 0));
        $this->get('/admin/inventory/recipes/'.$recipe->id.'?view=retired')->assertInertia(fn (AssertableInertia $p) => $p->where('versions.total', 1)->where('versions.data.0.id', $version->id));
        $this->get('/admin/inventory/recipes')->assertInertia(fn (AssertableInertia $p) => $p->where('recipes.data.0.is_active', false));
        $this->get('/admin/inventory/production/create')->assertInertia(fn (AssertableInertia $p) => $p->has('versions', 0));

        $this->post('/admin/inventory/recipes/'.$recipe->id.'/retire')->assertSessionHasErrors(['recipe' => 'Esta receta ya está retirada.']);
        $this->post('/admin/inventory/recipes/'.$recipe->id.'/versions', $this->formula($output, $onion))
            ->assertSessionHasErrors(['recipe' => 'Esta receta está retirada y no admite versiones nuevas. Si necesitás la fórmula, creá una receta nueva.']);
        $this->assertSame(1, $recipe->versions()->count());

        try {
            $this->produce($admin, $version);
            $this->fail('A retired recipe was used for production.');
        } catch (ValidationException) {
            $this->assertSame(0, ProductionBatch::query()->count());
        }
    }

    public function test_retired_recipe_history_with_production_stays_readable(): void
    {
        [$admin, , , , $recipe, $version] = $this->fixture();
        $batch = $this->produce($admin, $version);

        $this->actingAs($admin)->post('/admin/inventory/recipes/'.$recipe->id.'/retire')->assertSessionHasNoErrors();

        $this->get('/admin/inventory/production/'.$batch->id)->assertOk();
        $this->get('/admin/inventory/recipes/'.$recipe->id.'?view=retired')->assertInertia(fn (AssertableInertia $p) => $p->where('versions.total', 1));
    }

    public function test_items_used_only_by_retired_recipes_can_change_decimals_but_not_unit(): void
    {
        [$admin, $onion, , $output, $recipe] = $this->fixture(withStock: false);
        $values = $this->itemValues($onion);
        $this->actingAs($admin)->put('/admin/inventory/items/'.$onion->id, array_replace($values, ['quantity_precision' => 0]))
            ->assertSessionHasErrors(['measurement' => 'Los decimales no se pueden cambiar mientras una receta actual use este insumo. Retirá esa receta (o corregila si todavía no se usó en producción) y volvé a intentar.']);

        $this->post('/admin/inventory/recipes/'.$recipe->id.'/retire')->assertSessionHasNoErrors();

        $this->put('/admin/inventory/items/'.$onion->id, array_replace($values, ['quantity_precision' => 0]))->assertSessionHasNoErrors();
        $this->assertSame(0, $onion->fresh()->quantity_precision);
        $this->put('/admin/inventory/items/'.$output->id, array_replace($this->itemValues($output), ['quantity_precision' => 1]))->assertSessionHasNoErrors();
        $this->assertSame(1, $output->fresh()->quantity_precision);

        $this->put('/admin/inventory/items/'.$onion->id, array_replace($values, ['quantity_precision' => 0, 'unit_family' => 'volume', 'base_unit_id' => $this->unit('ml')->id, 'default_input_unit_id' => $this->unit('ml')->id]))
            ->assertSessionHasErrors(['measurement' => 'La unidad y el control de stock de este insumo no se pueden cambiar porque una receta (actual o retirada) lo usa. Si la unidad está mal, creá un insumo nuevo con la unidad correcta.']);
        $this->assertSame('weight', $onion->fresh()->unit_family->value);
    }

    public function test_stock_movements_still_lock_decimals_after_retirement(): void
    {
        [$admin, $onion, , , $recipe] = $this->fixture();
        $this->actingAs($admin)->post('/admin/inventory/recipes/'.$recipe->id.'/retire')->assertSessionHasNoErrors();

        $this->put('/admin/inventory/items/'.$onion->id, array_replace($this->itemValues($onion), ['quantity_precision' => 0]))
            ->assertSessionHasErrors(['measurement' => 'La medida de este insumo no se puede cambiar porque ya tiene movimientos de stock. Si la cargaste mal, creá un insumo nuevo con la medida correcta.']);
        $this->assertSame(3, $onion->fresh()->quantity_precision);
    }

    public function test_database_allows_decimals_only_for_items_referenced_by_retired_recipes(): void
    {
        [$admin, $onion, $beef, , $recipe, $version] = $this->fixture(withStock: false);

        try {
            DB::transaction(fn () => DB::table('inventory_items')->where('id', $onion->id)->update(['quantity_precision' => 0]));
            $this->fail('An active recipe ingredient changed its decimals.');
        } catch (QueryException) {
        }

        DB::transaction(function () use ($recipe, $version): void {
            DB::table('inventory_recipes')->where('id', $recipe->id)->update(['is_active' => false]);
            DB::table('inventory_recipe_versions')->where('id', $version->id)->update(['is_active' => false]);
        });
        DB::table('inventory_items')->where('id', $onion->id)->update(['quantity_precision' => 0]);
        $this->assertSame(0, $onion->fresh()->quantity_precision);

        $this->expectException(QueryException::class);
        DB::table('inventory_items')->where('id', $beef->id)->update(['base_unit_id' => $this->unit('kg')->id]);
    }

    public function test_correcting_an_unused_recipe_replaces_the_formula_without_history(): void
    {
        [$admin, $onion, $beef, $output, $recipe, $version] = $this->fixture(withStock: false);

        $this->actingAs($admin)->get('/admin/inventory/recipes/'.$recipe->id)->assertInertia(fn (AssertableInertia $p) => $p->where('can_correct', true));

        $this->put('/admin/inventory/recipes/'.$recipe->id.'/current-version', ['name' => 'Relleno Criolla Picante'] + $this->formula($output, $onion, '3'))
            ->assertRedirect('/admin/inventory/recipes/'.$recipe->id)
            ->assertSessionHasNoErrors();

        $this->assertSame('Relleno Criolla Picante', $recipe->fresh()->name);
        $this->assertNull(RecipeVersion::query()->find($version->id));
        $current = $recipe->versions()->sole();
        $this->assertSame(1, $current->version);
        $this->assertTrue($current->is_active);
        $this->assertSame('Relleno Criolla Picante', $current->recipe_name_snapshot);
        $this->assertSame('3000.000000', $current->expected_output_base_quantity);
        $this->assertSame([$onion->id], $current->ingredients()->pluck('inventory_item_id')->all());

        $this->put('/admin/inventory/items/'.$beef->id, array_replace($this->itemValues($beef), ['unit_family' => 'count', 'base_unit_id' => $this->unit('unit')->id, 'default_input_unit_id' => $this->unit('unit')->id, 'quantity_precision' => 0]))
            ->assertSessionHasNoErrors();
    }

    public function test_correction_is_refused_once_the_formula_was_used_or_the_recipe_retired(): void
    {
        [$admin, $onion, , $output, $recipe, $version] = $this->fixture();
        $this->produce($admin, $version);

        $this->actingAs($admin)->get('/admin/inventory/recipes/'.$recipe->id)->assertInertia(fn (AssertableInertia $p) => $p->where('can_correct', false));
        $this->put('/admin/inventory/recipes/'.$recipe->id.'/current-version', ['name' => 'Otro nombre'] + $this->formula($output, $onion))
            ->assertSessionHasErrors(['recipe' => 'Esta versión ya se usó en producción y no se puede corregir. Usá «Crear nueva versión» para cambiar la fórmula.']);
        $this->assertSame('Relleno Criolla Dulce', $recipe->fresh()->name);
        $this->assertSame([$version->id], $recipe->versions()->pluck('id')->all());

        [, $onion2, , $output2, $unused] = $this->fixture(prefix: 'B ', withStock: false);
        $this->post('/admin/inventory/recipes/'.$unused->id.'/retire')->assertSessionHasNoErrors();
        $this->put('/admin/inventory/recipes/'.$unused->id.'/current-version', ['name' => 'B nueva'] + $this->formula($output2, $onion2))
            ->assertSessionHasErrors(['recipe' => 'Esta receta está retirada y no se puede corregir.']);
    }

    public function test_correction_validates_name_and_formula_without_losing_the_current_version(): void
    {
        [$admin, $onion, , $output, $recipe, $version] = $this->fixture(withStock: false);
        app(CreateRecipe::class)->execute('Masa', $admin->id);

        $this->actingAs($admin)->put('/admin/inventory/recipes/'.$recipe->id.'/current-version', ['name' => ' masa '] + $this->formula($output, $onion))
            ->assertSessionHasErrors(['name' => 'Ya existe una receta con ese nombre (puede estar retirada). Elegí otro nombre.']);
        $this->put('/admin/inventory/recipes/'.$recipe->id.'/current-version', ['name' => 'Relleno'] + $this->formula($output, $output))
            ->assertSessionHasErrors('ingredients.0.inventory_item_id');

        $this->assertSame('Relleno Criolla Dulce', $recipe->fresh()->name);
        $this->assertSame([$version->id], $recipe->versions()->pluck('id')->all());
        $this->assertSame(2, $version->ingredients()->count());
    }

    public function test_database_deletes_only_unused_recipe_formulas(): void
    {
        [$admin, , , , , $used] = $this->fixture();
        $this->produce($admin, $used);

        try {
            DB::transaction(fn () => DB::table('inventory_recipe_ingredients')->where('recipe_version_id', $used->id)->delete());
            $this->fail('A used recipe formula lost its ingredients.');
        } catch (QueryException) {
            $this->assertSame(2, $used->ingredients()->count());
        }

        [, , , , , $unused] = $this->fixture(prefix: 'B ', withStock: false);
        try {
            DB::transaction(function () use ($unused): void {
                DB::table('inventory_recipe_ingredients')->where('recipe_version_id', $unused->id)->limit(1)->delete();
                DB::statement('SET CONSTRAINTS inventory_recipe_ingredient_removed IMMEDIATE');
            });
            $this->fail('A kept recipe formula lost one ingredient.');
        } catch (QueryException) {
            $this->assertSame(2, $unused->ingredients()->count());
        } finally {
            DB::statement('SET CONSTRAINTS inventory_recipe_ingredient_removed DEFERRED');
        }
        DB::transaction(function () use ($unused): void {
            DB::table('inventory_recipe_ingredients')->where('recipe_version_id', $unused->id)->delete();
            DB::table('inventory_recipe_versions')->where('id', $unused->id)->delete();
            DB::statement('SET CONSTRAINTS inventory_recipe_ingredient_removed IMMEDIATE');
        });
        DB::statement('SET CONSTRAINTS inventory_recipe_ingredient_removed DEFERRED');
        $this->assertNull(RecipeVersion::query()->find($unused->id));

        $this->expectException(QueryException::class);
        DB::table('inventory_recipe_ingredients')->where('recipe_version_id', $used->id)->update(['quantity' => '3']);
    }

    public function test_staff_cannot_retire_or_correct_recipes(): void
    {
        [, $onion, , $output, $recipe] = $this->fixture(withStock: false);
        foreach ([User::factory()->withRole(Role::Waiter)->create(), User::factory()->withRole(Role::Cashier)->create()] as $staff) {
            $this->actingAs($staff)->post('/admin/inventory/recipes/'.$recipe->id.'/retire')->assertForbidden();
            $this->put('/admin/inventory/recipes/'.$recipe->id.'/current-version', ['name' => 'X'] + $this->formula($output, $onion))->assertForbidden();
        }
        $this->assertTrue($recipe->fresh()->is_active);
    }

    /** @return array{User,InventoryItem,InventoryItem,InventoryItem,Recipe,RecipeVersion} */
    private function fixture(string $prefix = '', bool $withStock = true): array
    {
        $admin = User::factory()->administrator()->create();
        $onion = $this->item($prefix.'Cebolla');
        $beef = $this->item($prefix.'Roast Beef');
        $output = $this->item($prefix.'Relleno Criolla Dulce', true, true);
        if ($withStock) {
            $this->receipt($admin, $onion, '1000', 2150);
            $this->receipt($admin, $beef, '1000', 14500);
        }
        $recipe = app(CreateRecipe::class)->execute($prefix.'Relleno Criolla Dulce', $admin->id);
        $v = app(CreateRecipeVersion::class)->execute($recipe->id, new RecipeVersionData($output->id, $this->unit('kg')->id, '2', [new RecipeIngredientData($onion->id, $this->unit('kg')->id, '1'), new RecipeIngredientData($beef->id, $this->unit('kg')->id, '1')]), $admin->id);

        return [$admin, $onion, $beef, $output, $recipe, $v];
    }

    /** @return array<string, mixed> */
    private function formula(InventoryItem $output, InventoryItem $ingredient, string $expected = '2'): array
    {
        return ['output_inventory_item_id' => $output->id, 'output_unit_id' => $this->unit('kg')->id, 'expected_output_quantity' => $expected,
            'ingredients' => [['inventory_item_id' => $ingredient->id, 'unit_id' => $this->unit('kg')->id, 'quantity' => '1']]];
    }

    /** @return array<string, mixed> */
    private function itemValues(InventoryItem $item): array
    {
        return ['category_id' => $item->inventory_category_id, 'name' => $item->name, 'tracks_stock' => true, 'unit_family' => 'weight',
            'base_unit_id' => $item->base_unit_id, 'default_input_unit_id' => $item->default_input_unit_id, 'quantity_precision' => $item->quantity_precision,
            'is_active' => true, 'kind' => $item->kind, 'recipe_input_enabled' => $item->recipe_input_enabled,
            'production_output_enabled' => $item->production_output_enabled, 'order_consumption_enabled' => $item->order_consumption_enabled];
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
}
