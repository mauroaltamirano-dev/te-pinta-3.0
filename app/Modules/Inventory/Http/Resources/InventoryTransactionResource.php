<?php

namespace App\Modules\Inventory\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class InventoryTransactionResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->id,
            'type' => $this->resource->type->value,
            'effective_date' => $this->resource->effective_date->toDateString(),
            'source_type' => $this->resource->source_type?->value,
            'source_id' => $this->resource->source_id,
            'description' => $this->resource->description,
            'movements' => $this->resource->movements->map(fn ($movement): array => [
                'id' => $movement->id, 'quantity' => $movement->quantity,
                'inventory_item' => [
                    'id' => $movement->item->id, 'name' => $movement->item->name,
                    'base_unit' => (new InventoryUnitResource($movement->unit))->resolve($request),
                ],
            ])->all(),
        ];
    }
}
