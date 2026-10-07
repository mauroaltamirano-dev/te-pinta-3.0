<?php

namespace App\Modules\Inventory\Support;

use App\Modules\Inventory\Data\InventoryItemData;
use App\Modules\Inventory\Enums\UnitFamily;
use App\Modules\Inventory\Models\InventoryCategory;
use App\Modules\Inventory\Models\InventoryItem;
use App\Modules\Inventory\Models\InventoryUnit;
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
            $measurementChanged = $current->unit_family !== $data->unitFamily
                || $current->base_unit_id !== $data->baseUnitId
                || $current->quantity_precision !== $data->quantityPrecision;
            $stockIdentityChanged = $measurementChanged || $current->tracks_stock !== $data->tracksStock;

            if ($stockIdentityChanged && $current->stockMovements()->exists()) {
                $errors['measurement'] = 'Stock measurement identity cannot change after movements exist.';
            } elseif ($measurementChanged && $current->presentations()->exists()) {
                $errors['measurement'] = 'Item measurement cannot change while purchase presentations exist.';
            }
        }

        if ($category === null || (! $category->is_active && $current?->inventory_category_id !== $category->id)) {
            $errors['category_id'] = 'The inventory category must be active.';
        }
        if ($baseUnit === null || ! $baseUnit->is_base || $baseUnit->family !== $data->unitFamily) {
            $errors['base_unit_id'] = 'The base unit must be the base of the selected family.';
        }
        if ($inputUnit === null || $inputUnit->family !== $data->unitFamily) {
            $errors['default_input_unit_id'] = 'The input unit must belong to the selected family.';
        }
        if ($data->quantityPrecision < 0 || $data->quantityPrecision > 6) {
            $errors['quantity_precision'] = 'Quantity precision must be between zero and six.';
        }
        if ($data->kind !== null && ! in_array($data->kind, ['raw_material', 'semi_finished', 'finished', 'packaging', 'consumable', 'other'], true)) {
            $errors['kind'] = 'Invalid inventory item kind.';
        }
        if ($data->referenceMassGramsPerUnit !== null) {
            if ($data->unitFamily !== UnitFamily::Count) {
                $errors['reference_mass_grams_per_unit'] = 'Reference mass applies only to count-based items.';
            } elseif (! preg_match('/^(?:0|[1-9][0-9]{0,13})(?:\.[0-9]{1,6})?$/D', $data->referenceMassGramsPerUnit)
                || DecimalMath::compare($data->referenceMassGramsPerUnit, '0') <= 0) {
                $errors['reference_mass_grams_per_unit'] = 'Reference mass must be a positive decimal with at most six places.';
            }
        }
        if (trim($data->name) === '') {
            $errors['name'] = 'The item name is required.';
        } elseif (InventoryItem::query()
            ->when($current !== null, fn ($query) => $query->whereKeyNot($current->id))
            ->whereRaw('lower(name) = lower(?)', [trim($data->name)])
            ->exists()) {
            $errors['name'] = 'The inventory item name is already in use.';
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
