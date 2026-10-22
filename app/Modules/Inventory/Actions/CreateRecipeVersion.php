<?php

namespace App\Modules\Inventory\Actions;

use App\Modules\Inventory\Data\RecipeVersionData;
use App\Modules\Inventory\Models\InventoryItem;
use App\Modules\Inventory\Models\InventoryUnit;
use App\Modules\Inventory\Models\Recipe;
use App\Modules\Inventory\Models\RecipeIngredient;
use App\Modules\Inventory\Models\RecipeVersion;
use App\Modules\Inventory\Support\UnitConverter;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class CreateRecipeVersion
{
    public function __construct(private readonly UnitConverter $converter) {}

    public function execute(int $recipeId, RecipeVersionData $data, int $adminId): RecipeVersion
    {
        return DB::transaction(function () use ($recipeId, $data, $adminId) {
            DB::select('SELECT pg_advisory_xact_lock(hashtextextended(?, 0))', ['inventory:recipe-graph']);
            $recipe = Recipe::query()->lockForUpdate()->findOrFail($recipeId);
            if (! $recipe->is_active) {
                throw ValidationException::withMessages(['recipe' => 'Esta receta está retirada y no admite versiones nuevas. Si necesitás la fórmula, creá una receta nueva.']);
            }
            $existingVersions = RecipeVersion::query()
                ->where('recipe_id', $recipe->id)
                ->orderBy('id')
                ->orderBy('version')
                ->lockForUpdate()
                ->get(['id', 'version', 'is_active']);
            $next = ((int) $existingVersions->max('version')) + 1;
            $activeVersionIds = $existingVersions
                ->where('is_active', true)
                ->pluck('id')
                ->all();
            $ids = collect($data->ingredients)->pluck('inventoryItemId')->push($data->outputInventoryItemId)->unique()->sort()->values();
            $items = InventoryItem::query()->whereKey($ids)->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            $unitIds = collect($data->ingredients)->pluck('unitId')->push($data->outputUnitId)->unique()->sort()->values();
            $units = InventoryUnit::query()->whereKey($unitIds)->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            if ($items->count() !== $ids->count() || $units->count() !== $unitIds->count()) {
                throw ValidationException::withMessages(['recipe' => 'Algún insumo o unidad de la receta ya no existe. Recargá la página y elegilos de nuevo.']);
            }
            foreach ($items as $item) {
                if (! $item->is_active || ! $item->tracks_stock) {
                    throw ValidationException::withMessages(['recipe' => 'Solo se pueden usar insumos activos que controlan stock. Revisá los insumos elegidos.']);
                }
            }
            $output = $items[$data->outputInventoryItemId];
            if (! $output->production_output_enabled) {
                throw ValidationException::withMessages(['output_inventory_item_id' => 'Este insumo no tiene activada «Salida de producción». Activala en el insumo o elegí otro.']);
            }
            $expected = $this->converter->toBase($output, $units[$data->outputUnitId], $data->expectedOutputQuantity);
            $ingredientIds = [];
            foreach ($data->ingredients as $i => $ingredient) {
                if (isset($ingredientIds[$ingredient->inventoryItemId])) {
                    throw ValidationException::withMessages(["ingredients.$i.inventory_item_id" => 'Este ingrediente está repetido. Dejalo una sola vez y sumá las cantidades.']);
                }
                if ($ingredient->inventoryItemId === $output->id) {
                    throw ValidationException::withMessages(["ingredients.$i.inventory_item_id" => 'El insumo que produce la receta no puede ser también su ingrediente.']);
                }
                $ingredientIds[$ingredient->inventoryItemId] = true;
                if (! $items[$ingredient->inventoryItemId]->recipe_input_enabled) {
                    throw ValidationException::withMessages(["ingredients.$i.inventory_item_id" => 'Este insumo no tiene activado «Usar en recetas». Activalo en el insumo o elegí otro.']);
                }
                $this->converter->toBase($items[$ingredient->inventoryItemId], $units[$ingredient->unitId], $ingredient->quantity);
            }
            if ($ingredientIds === []) {
                throw ValidationException::withMessages(['ingredients' => 'Agregá al menos un ingrediente.']);
            }
            foreach (array_keys($ingredientIds) as $id) {
                if ($this->wouldCreateCycle($output->id, $id, $recipe->id)) {
                    throw ValidationException::withMessages(['ingredients' => 'Esta fórmula formaría un círculo entre recetas (un insumo terminaría siendo ingrediente de sí mismo). Revisá los ingredientes.']);
                }
            }
            if ($activeVersionIds !== []) {
                RecipeVersion::query()->whereKey($activeVersionIds)->update(['is_active' => false]);
            }
            $version = RecipeVersion::query()->create(['recipe_id' => $recipe->id, 'version' => $next, 'recipe_name_snapshot' => $recipe->name, 'output_inventory_item_id' => $output->id, 'expected_output_quantity' => $data->expectedOutputQuantity, 'output_unit_id' => $data->outputUnitId, 'expected_output_base_quantity' => $expected, 'is_active' => false, 'is_sealed' => false, 'created_by' => $adminId]);
            foreach ($data->ingredients as $ingredient) {
                RecipeIngredient::query()->create(['recipe_version_id' => $version->id, 'inventory_item_id' => $ingredient->inventoryItemId, 'quantity' => $ingredient->quantity, 'unit_id' => $ingredient->unitId, 'base_quantity' => $this->converter->toBase($items[$ingredient->inventoryItemId], $units[$ingredient->unitId], $ingredient->quantity)]);
            }
            $version->update(['is_sealed' => true, 'is_active' => true]);
            logger()->info('[Inventory] recipe.version_created', ['recipe_id' => $recipe->id, 'recipe_version_id' => $version->id, 'admin_id' => $adminId]);

            return $version->load('ingredients.item.baseUnit', 'outputItem.baseUnit', 'outputUnit');
        });
    }

    private function wouldCreateCycle(int $outputId, int $ingredientId, int $replacingRecipeId): bool
    {
        $rows = DB::select('WITH RECURSIVE graph AS (SELECT rv.output_inventory_item_id output_id, ri.inventory_item_id input_id FROM inventory_recipe_versions rv JOIN inventory_recipe_ingredients ri ON ri.recipe_version_id=rv.id WHERE rv.is_active AND rv.recipe_id<>? UNION SELECT g.output_id, ri.inventory_item_id FROM graph g JOIN inventory_recipe_versions rv ON rv.output_inventory_item_id=g.input_id AND rv.is_active AND rv.recipe_id<>? JOIN inventory_recipe_ingredients ri ON ri.recipe_version_id=rv.id) SELECT 1 FROM graph WHERE output_id=? AND input_id=? LIMIT 1', [$replacingRecipeId, $replacingRecipeId, $ingredientId, $outputId]);

        return $rows !== [];
    }
}
