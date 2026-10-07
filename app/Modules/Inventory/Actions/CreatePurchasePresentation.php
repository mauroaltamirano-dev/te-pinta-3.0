<?php

namespace App\Modules\Inventory\Actions;

use App\Modules\Inventory\Data\PurchasePresentationData;
use App\Modules\Inventory\Models\InventoryItem;
use App\Modules\Inventory\Models\PurchasePresentation;
use App\Modules\Inventory\Support\PurchasePresentationValidator;
use Illuminate\Support\Facades\DB;

final class CreatePurchasePresentation
{
    public function __construct(private readonly PurchasePresentationValidator $validator) {}

    public function execute(PurchasePresentationData $data): PurchasePresentation
    {
        return DB::transaction(function () use ($data): PurchasePresentation {
            InventoryItem::query()->whereKey($data->inventoryItemId)->lockForUpdate()->first();

            return PurchasePresentation::query()->create($this->validator->validatedAttributes($data))
                ->load('item', 'contentUnit');
        });
    }
}
