<?php

namespace App\Modules\Inventory\Data;

final readonly class RecipeVersionData
{
    /** @param list<RecipeIngredientData> $ingredients */
    public function __construct(public int $outputInventoryItemId, public int $outputUnitId, public string $expectedOutputQuantity, public array $ingredients) {}
}
