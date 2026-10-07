<?php

namespace App\Modules\Inventory\Data;

final readonly class PurchasePresentationData
{
    public function __construct(
        public int $inventoryItemId,
        public string $name,
        public string $contentQuantity,
        public int $contentUnitId,
        public bool $isActive = true,
    ) {}
}
