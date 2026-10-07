<?php

namespace App\Modules\Inventory\Data;

use App\Modules\Inventory\Enums\InventorySourceType;
use App\Modules\Inventory\Enums\InventoryTransactionType;
use Carbon\CarbonImmutable;

final readonly class InventoryTransactionData
{
    /** @param list<InventoryMovementData> $movements */
    public function __construct(
        public InventoryTransactionType $type,
        public CarbonImmutable $effectiveDate,
        public array $movements,
        public ?InventorySourceType $sourceType = null,
        public ?string $sourceId = null,
        public ?string $description = null,
        public ?int $reversalOfId = null,
    ) {}
}
