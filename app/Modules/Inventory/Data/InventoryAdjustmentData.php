<?php

namespace App\Modules\Inventory\Data;

use Carbon\CarbonImmutable;

final readonly class InventoryAdjustmentData
{
    public function __construct(public int $inventoryItemId, public int $unitId, public string $type, public string $quantity, public CarbonImmutable $effectiveDate, public string $idempotencyKey, public ?int $explicitTotalCost = null, public ?string $note = null) {}
}
