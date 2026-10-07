<?php

namespace App\Modules\Inventory\Data;

use App\Modules\Inventory\Enums\UnitFamily;

final readonly class InventoryItemData
{
    public function __construct(
        public int $categoryId,
        public string $name,
        public bool $tracksStock,
        public UnitFamily $unitFamily,
        public int $baseUnitId,
        public int $defaultInputUnitId,
        public int $quantityPrecision,
        public bool $isActive = true,
        public ?string $kind = null,
        public bool $recipeInputEnabled = false,
        public bool $productionOutputEnabled = false,
        public bool $orderConsumptionEnabled = false,
        public ?string $referenceMassGramsPerUnit = null,
    ) {}
}
