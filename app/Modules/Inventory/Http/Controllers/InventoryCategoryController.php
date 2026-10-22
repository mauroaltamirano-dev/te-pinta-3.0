<?php

namespace App\Modules\Inventory\Http\Controllers;

use App\Modules\Inventory\Actions\CreateInventoryCategory;
use App\Modules\Inventory\Actions\UpdateInventoryCategory;
use App\Modules\Inventory\Http\Requests\StoreInventoryCategoryRequest;
use App\Modules\Inventory\Http\Requests\UpdateInventoryCategoryRequest;
use App\Modules\Inventory\Models\InventoryCategory;
use Illuminate\Http\RedirectResponse;

final class InventoryCategoryController
{
    public function store(
        StoreInventoryCategoryRequest $request,
        CreateInventoryCategory $createCategory,
    ): RedirectResponse {
        $createCategory->execute($request->string('name')->toString());

        return back(fallback: route('admin.inventory.index'))->with('success', 'Categoría de inventario creada.');
    }

    public function update(
        UpdateInventoryCategoryRequest $request,
        InventoryCategory $inventoryCategory,
        UpdateInventoryCategory $updateCategory,
    ): RedirectResponse {
        $updateCategory->execute(
            $inventoryCategory,
            $request->string('name')->toString(),
            $request->boolean('is_active'),
        );

        return back(fallback: route('admin.inventory.index'))->with('success', 'Categoría de inventario actualizada.');
    }
}
