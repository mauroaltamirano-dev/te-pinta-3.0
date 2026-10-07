<?php

namespace App\Modules\Inventory\Data;

final readonly class InventoryMovementData
{
    public function __construct(
        public int $inventoryItemId,
        public string $quantity,
        public ?int $totalCost = null,
        public ?int $reversalOfCostLayerId = null,
    ) {}
}
