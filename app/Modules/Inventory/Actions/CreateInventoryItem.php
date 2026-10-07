<?php

namespace App\Modules\Inventory\Actions;

use App\Modules\Inventory\Data\InventoryItemData;
use App\Modules\Inventory\Models\InventoryItem;
use App\Modules\Inventory\Support\InventoryItemValidator;

final class CreateInventoryItem
{
    public function __construct(private readonly InventoryItemValidator $validator) {}

    public function execute(InventoryItemData $data): InventoryItem
    {
        return InventoryItem::query()->create($this->validator->validatedAttributes($data))
            ->load('category', 'baseUnit', 'defaultInputUnit');
    }
}
