<?php

namespace App\Modules\Inventory\Support;

use App\Modules\Inventory\Data\PurchasePresentationData;
use App\Modules\Inventory\Models\InventoryItem;
use App\Modules\Inventory\Models\InventoryUnit;
use App\Modules\Inventory\Models\PurchasePresentation;
use Illuminate\Validation\ValidationException;

final class PurchasePresentationValidator
{
    public function __construct(private readonly UnitConverter $converter) {}

    /** @return array<string, mixed> */
    public function validatedAttributes(PurchasePresentationData $data, ?PurchasePresentation $current = null): array
    {
        $item = InventoryItem::query()->find($data->inventoryItemId);
        $unit = InventoryUnit::query()->find($data->contentUnitId);
        if ($item === null || (! $item->is_active && $current?->inventory_item_id !== $item->id)) {
            throw ValidationException::withMessages(['inventory_item_id' => 'The inventory item must be active.']);
        }
        if ($unit === null) {
            throw ValidationException::withMessages(['content_unit_id' => 'The content unit is invalid.']);
        }
        if (trim($data->name) === '') {
            throw ValidationException::withMessages(['name' => 'The presentation name is required.']);
        }
        if (PurchasePresentation::query()
            ->where('inventory_item_id', $item->id)
            ->when($current !== null, fn ($query) => $query->whereKeyNot($current->id))
            ->whereRaw('lower(name) = lower(?)', [trim($data->name)])
            ->exists()) {
            throw ValidationException::withMessages(['name' => 'The presentation name is already in use for this item.']);
        }
        $this->converter->toBase($item, $unit, $data->contentQuantity);

        return [
            'inventory_item_id' => $item->id,
            'name' => trim($data->name),
            'content_quantity' => $data->contentQuantity,
            'content_unit_id' => $unit->id,
            'is_active' => $data->isActive,
        ];
    }
}
