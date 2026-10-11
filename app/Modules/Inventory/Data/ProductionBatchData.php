<?php

namespace App\Modules\Inventory\Data;

use Carbon\CarbonImmutable;

final readonly class ProductionBatchData
{
    public function __construct(public int $recipeVersionId, public int $outputUnitId, public string $actualOutputQuantity, public CarbonImmutable $effectiveDate, public string $idempotencyKey, public ?string $notes = null) {}
}
