<?php

namespace App\Modules\Inventory\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class InventoryItemResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->id,
            'category_id' => $this->resource->inventory_category_id,
            'name' => $this->resource->name,
            'tracks_stock' => $this->resource->tracks_stock,
            'unit_family' => $this->resource->unit_family->value,
            'base_unit_id' => $this->resource->base_unit_id,
            'default_input_unit_id' => $this->resource->default_input_unit_id,
            'quantity_precision' => $this->resource->quantity_precision,
            'is_active' => $this->resource->is_active,
            'kind' => $this->resource->kind,
            'classification_pending' => $this->resource->kind === null,
            'recipe_input_enabled' => $this->resource->recipe_input_enabled,
            'production_output_enabled' => $this->resource->production_output_enabled,
            'order_consumption_enabled' => $this->resource->order_consumption_enabled,
            'reference_mass_grams_per_unit' => $this->resource->reference_mass_grams_per_unit,
            'stock_quantity' => $this->resource->stock_quantity ?? '0.000000',
            'valuation' => $this->resource->getAttribute('valuation') ?? ['coverage' => 'unknown', 'value' => null, 'cost_quantity' => '0', 'cost_value' => 0],
            'category' => [
                'id' => $this->resource->category->id,
                'name' => $this->resource->category->name,
                'is_active' => $this->resource->category->is_active,
            ],
            'base_unit' => (new InventoryUnitResource($this->resource->baseUnit))->resolve($request),
            'default_input_unit' => (new InventoryUnitResource($this->resource->defaultInputUnit))->resolve($request),
            'presentations' => $this->resource->presentations
                ->map(fn ($presentation): array => [
                    'id' => $presentation->id,
                    'inventory_item_id' => $presentation->inventory_item_id,
                    'name' => $presentation->name,
                    'content_quantity' => $presentation->content_quantity,
                    'content_unit_id' => $presentation->content_unit_id,
                    'content_unit' => (new InventoryUnitResource($presentation->contentUnit))->resolve($request),
                    'is_active' => $presentation->is_active,
                ])->all(),
        ];
    }
}
