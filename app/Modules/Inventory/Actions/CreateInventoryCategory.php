<?php

namespace App\Modules\Inventory\Actions;

use App\Modules\Inventory\Models\InventoryCategory;
use Illuminate\Validation\ValidationException;

final class CreateInventoryCategory
{
    public function execute(string $name): InventoryCategory
    {
        $name = trim($name);
        if (InventoryCategory::query()->whereRaw('lower(name) = lower(?)', [$name])->exists()) {
            throw ValidationException::withMessages(['name' => 'Ya existe una categoría de inventario con ese nombre. Usá otro nombre.']);
        }

        return InventoryCategory::query()->create(['name' => $name, 'is_active' => true]);
    }
}
