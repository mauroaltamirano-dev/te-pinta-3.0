<?php

namespace App\Modules\Inventory\Actions;

use App\Modules\Inventory\Data\InventoryAdjustmentData;

final class PreviewInventoryAdjustment
{
    public function __construct(private readonly CreateInventoryAdjustment $create) {}

    /** @return array<string,mixed> */
    public function execute(InventoryAdjustmentData $data): array
    {
        return $this->create->preview($data);
    }
}
