<?php

namespace App\Modules\Inventory\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class InventoryUnitResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->id,
            'code' => $this->resource->code,
            'name' => $this->resource->name,
            'symbol' => $this->resource->symbol,
            'family' => $this->resource->family->value,
            'factor_to_base' => $this->resource->factor_to_base,
            'is_base' => $this->resource->is_base,
        ];
    }
}
