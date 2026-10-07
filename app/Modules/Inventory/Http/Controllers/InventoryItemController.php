<?php

namespace App\Modules\Inventory\Http\Controllers;

use App\Modules\Inventory\Actions\CreateInventoryItem;
use App\Modules\Inventory\Actions\UpdateInventoryItem;
use App\Modules\Inventory\Data\InventoryItemData;
use App\Modules\Inventory\Enums\UnitFamily;
use App\Modules\Inventory\Http\Requests\StoreInventoryItemRequest;
use App\Modules\Inventory\Http\Requests\UpdateInventoryItemRequest;
use App\Modules\Inventory\Models\InventoryItem;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\RedirectResponse;

final class InventoryItemController
{
    public function store(
        StoreInventoryItemRequest $request,
        CreateInventoryItem $createItem,
    ): RedirectResponse {
        $createItem->execute($this->data($request));

        return to_route('admin.inventory.index')->with('success', 'Item de inventario creado.');
    }

    public function update(
        UpdateInventoryItemRequest $request,
        InventoryItem $inventoryItem,
        UpdateInventoryItem $updateItem,
    ): RedirectResponse {
        $updateItem->execute($inventoryItem, $this->data($request));

        return to_route('admin.inventory.index')->with('success', 'Item de inventario actualizado.');
    }

    private function data(FormRequest $request): InventoryItemData
    {
        return new InventoryItemData(
            categoryId: $request->integer('category_id'),
            name: $request->string('name')->toString(),
            tracksStock: $request->boolean('tracks_stock'),
            unitFamily: UnitFamily::from($request->string('unit_family')->toString()),
            baseUnitId: $request->integer('base_unit_id'),
            defaultInputUnitId: $request->integer('default_input_unit_id'),
            quantityPrecision: $request->integer('quantity_precision'),
            isActive: $request->boolean('is_active'),
            kind: $request->input('kind'),
            recipeInputEnabled: $request->boolean('recipe_input_enabled'),
            productionOutputEnabled: $request->boolean('production_output_enabled'),
            orderConsumptionEnabled: $request->boolean('order_consumption_enabled'),
            referenceMassGramsPerUnit: $request->filled('reference_mass_grams_per_unit') ? (string) $request->input('reference_mass_grams_per_unit') : null,
        );
    }
}
