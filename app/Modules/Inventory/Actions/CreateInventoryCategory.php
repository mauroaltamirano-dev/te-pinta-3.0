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
            throw ValidationException::withMessages(['name' => 'The inventory category name is already in use.']);
        }

        return InventoryCategory::query()->create(['name' => $name, 'is_active' => true]);
    }
}
