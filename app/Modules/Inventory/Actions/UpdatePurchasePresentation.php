<?php

namespace App\Modules\Inventory\Actions;

use App\Modules\Inventory\Data\PurchasePresentationData;
use App\Modules\Inventory\Models\InventoryItem;
use App\Modules\Inventory\Models\PurchasePresentation;
use App\Modules\Inventory\Support\PurchasePresentationValidator;
use Illuminate\Support\Facades\DB;

final class UpdatePurchasePresentation
{
    public function __construct(private readonly PurchasePresentationValidator $validator) {}

    public function execute(PurchasePresentation $presentation, PurchasePresentationData $data): PurchasePresentation
    {
        return DB::transaction(function () use ($presentation, $data): PurchasePresentation {
            $lockedPresentation = PurchasePresentation::query()->lockForUpdate()->findOrFail($presentation->id);
            InventoryItem::query()
                ->whereKey(array_values(array_unique([$lockedPresentation->inventory_item_id, $data->inventoryItemId])))
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            $lockedPresentation->update($this->validator->validatedAttributes($data, $lockedPresentation));

            return $lockedPresentation->refresh()->load('item', 'contentUnit');
        });
    }
}
