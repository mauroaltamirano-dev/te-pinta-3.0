<?php

namespace App\Modules\Management\Data;

use App\Modules\Management\Enums\PurchasePriceMode;

final readonly class PurchaseLineData
{
    public function __construct(
        public int $inventoryItemId,
        public ?int $purchasePresentationId,
        public ?int $inputUnitId,
        public string $quantity,
        public PurchasePriceMode $priceMode,
        public int $priceAmount,
    ) {}
}
