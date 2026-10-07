<?php

namespace App\Modules\Inventory\Actions;

use App\Modules\Inventory\Data\InventoryItemData;
use App\Modules\Inventory\Models\InventoryItem;
use App\Modules\Inventory\Support\InventoryItemValidator;
use Illuminate\Support\Facades\DB;

final class UpdateInventoryItem
{
    public function __construct(private readonly InventoryItemValidator $validator) {}

    public function execute(InventoryItem $item, InventoryItemData $data): InventoryItem
    {
        return DB::transaction(function () use ($item, $data): InventoryItem {
            $lockedItem = InventoryItem::query()->lockForUpdate()->findOrFail($item->id);
            $lockedItem->update($this->validator->validatedAttributes($data, $lockedItem));

            return $lockedItem->refresh()->load('category', 'baseUnit', 'defaultInputUnit');
        });
    }
}
