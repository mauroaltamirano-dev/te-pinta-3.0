<?php

namespace App\Modules\Inventory\Actions;

use App\Modules\Inventory\Models\InventoryCategory;
use Illuminate\Validation\ValidationException;

final class UpdateInventoryCategory
{
    public function execute(InventoryCategory $category, string $name, bool $isActive): InventoryCategory
    {
        $name = trim($name);
        if (InventoryCategory::query()->whereKeyNot($category->id)->whereRaw('lower(name) = lower(?)', [$name])->exists()) {
            throw ValidationException::withMessages(['name' => 'Ya existe una categoría de inventario con ese nombre. Usá otro nombre.']);
        }

        $category->update(['name' => $name, 'is_active' => $isActive]);

        return $category->refresh();
    }
}
