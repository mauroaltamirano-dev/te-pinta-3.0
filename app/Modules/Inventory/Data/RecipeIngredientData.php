<?php

namespace App\Modules\Inventory\Data;

final readonly class RecipeIngredientData
{
    public function __construct(public int $inventoryItemId, public int $unitId, public string $quantity) {}
}
