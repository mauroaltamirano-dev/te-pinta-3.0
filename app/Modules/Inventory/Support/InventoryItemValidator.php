<?php

namespace App\Modules\Inventory\Support;

use App\Modules\Inventory\Data\InventoryItemData;
use App\Modules\Inventory\Enums\UnitFamily;
use App\Modules\Inventory\Models\InventoryCategory;
use App\Modules\Inventory\Models\InventoryItem;
use App\Modules\Inventory\Models\InventoryUnit;
use App\Modules\Inventory\Models\RecipeIngredient;
use App\Modules\Inventory\Models\RecipeVersion;
use Illuminate\Validation\ValidationException;

final class InventoryItemValidator
{
    /** @return array<string, mixed> */
    public function validatedAttributes(InventoryItemData $data, ?InventoryItem $current = null): array
    {
        $category = InventoryCategory::query()->find($data->categoryId);
        $baseUnit = InventoryUnit::query()->find($data->baseUnitId);
        $inputUnit = InventoryUnit::query()->find($data->defaultInputUnitId);
        $errors = [];

        if ($current !== null) {
            $unitChanged = $current->unit_family !== $data->unitFamily || $current->base_unit_id !== $data->baseUnitId;
            $precisionChanged = $current->quantity_precision !== $data->quantityPrecision;
            $measurementChanged = $unitChanged || $precisionChanged;
            $stockIdentityChanged = $measurementChanged || $current->tracks_stock !== $data->tracksStock;
            $activeVersions = RecipeVersion::query()->select('id')->where('is_active', true);

            if (($unitChanged || $current->tracks_stock !== $data->tracksStock) && (RecipeVersion::query()->where('output_inventory_item_id', $current->id)->exists()
                || RecipeIngredient::query()->where('inventory_item_id', $current->id)->exists())) {
                $errors['measurement'] = 'La unidad y el control de stock de este insumo no se pueden cambiar porque una receta (actual o retirada) lo usa. Si la unidad está mal, creá un insumo nuevo con la unidad correcta.';
            } elseif ($precisionChanged && (RecipeVersion::query()->where('output_inventory_item_id', $current->id)->where('is_active', true)->exists()
                || RecipeIngredient::query()->where('inventory_item_id', $current->id)->whereIn('recipe_version_id', $activeVersions)->exists())) {
                $errors['measurement'] = 'Los decimales no se pueden cambiar mientras una receta actual use este insumo. Retirá esa receta (o corregila si todavía no se usó en producción) y volvé a intentar.';
            }
            if (! $data->productionOutputEnabled && RecipeVersion::query()->where('output_inventory_item_id', $current->id)->where('is_active', true)->exists()) {
                $errors['production_output_enabled'] = 'Una receta actual produce este insumo, así que necesita «Salida de producción». Retirá esa receta antes de desactivar esa opción.';
            }
            if (! $data->recipeInputEnabled && RecipeIngredient::query()->where('inventory_item_id', $current->id)->whereIn('recipe_version_id', $activeVersions)->exists()) {
                $errors['recipe_input_enabled'] = 'Una receta actual usa este insumo, así que necesita «Usar en recetas». Retirá esa receta o creá una versión nueva sin este insumo antes de desactivar esa opción.';
            }

            if ($stockIdentityChanged && $current->stockMovements()->exists()) {
                $errors['measurement'] = 'La medida de este insumo no se puede cambiar porque ya tiene movimientos de stock. Si la cargaste mal, creá un insumo nuevo con la medida correcta.';
            } elseif ($measurementChanged && $current->presentations()->exists()) {
                $errors['measurement'] = 'La medida de este insumo no se puede cambiar porque tiene presentaciones de compra. Si la cargaste mal, creá un insumo nuevo con la medida correcta.';
            }
        }

        if ($category === null || (! $category->is_active && $current?->inventory_category_id !== $category->id)) {
            $errors['category_id'] = 'Elegí una categoría activa.';
        }
        if ($baseUnit === null || ! $baseUnit->is_base || $baseUnit->family !== $data->unitFamily) {
            $errors['base_unit_id'] = 'Elegí una unidad base que corresponda al tipo de medida seleccionado.';
        }
        if ($inputUnit === null || $inputUnit->family !== $data->unitFamily) {
            $errors['default_input_unit_id'] = 'Elegí una unidad de carga que corresponda al tipo de medida seleccionado.';
        }
        if ($data->quantityPrecision < 0 || $data->quantityPrecision > 6) {
            $errors['quantity_precision'] = 'Los decimales deben ser un número entre 0 y 6.';
        }
        if ($data->kind !== null && ! in_array($data->kind, ['raw_material', 'semi_finished', 'finished', 'packaging', 'consumable', 'other'], true)) {
            $errors['kind'] = 'Elegí una clasificación de la lista.';
        }
        if ($data->referenceMassGramsPerUnit !== null) {
            if ($data->unitFamily !== UnitFamily::Count) {
                $errors['reference_mass_grams_per_unit'] = 'El peso de referencia solo se usa en insumos que se cuentan por unidad. Dejalo vacío.';
            } elseif (! preg_match('/^(?:0|[1-9][0-9]{0,13})(?:\.[0-9]{1,6})?$/D', $data->referenceMassGramsPerUnit)
                || DecimalMath::compare($data->referenceMassGramsPerUnit, '0') <= 0) {
                $errors['reference_mass_grams_per_unit'] = 'Ingresá un peso de referencia mayor a 0, con hasta 6 decimales.';
            }
        }
        if (trim($data->name) === '') {
            $errors['name'] = 'Ingresá el nombre del insumo.';
        } elseif (InventoryItem::query()
            ->when($current !== null, fn ($query) => $query->whereKeyNot($current->id))
            ->whereRaw('lower(name) = lower(?)', [trim($data->name)])
            ->exists()) {
            $errors['name'] = 'Ya existe un insumo con ese nombre. Elegí otro nombre.';
        }
        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        return [
            'inventory_category_id' => $data->categoryId,
            'name' => trim($data->name),
            'tracks_stock' => $data->tracksStock,
            'unit_family' => $data->unitFamily,
            'base_unit_id' => $data->baseUnitId,
            'default_input_unit_id' => $data->defaultInputUnitId,
            'quantity_precision' => $data->quantityPrecision,
            'is_active' => $data->isActive,
            'kind' => $data->kind,
            'recipe_input_enabled' => $data->recipeInputEnabled,
            'production_output_enabled' => $data->productionOutputEnabled,
            'order_consumption_enabled' => $data->orderConsumptionEnabled,
            'reference_mass_grams_per_unit' => $data->referenceMassGramsPerUnit,
        ];
    }
}
