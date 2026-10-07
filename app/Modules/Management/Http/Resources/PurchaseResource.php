<?php

namespace App\Modules\Management\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class PurchaseResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->id,
            'idempotency_key' => $this->resource->idempotency_key,
            'supplier_name' => $this->resource->supplier_name,
            'description' => $this->resource->description,
            'effective_date' => $this->resource->effective_date->toDateString(),
            'subtotal' => $this->resource->subtotal,
            'shipping_amount' => $this->resource->shipping_amount,
            'has_shipping' => $this->resource->shipping_amount > 0,
            'total' => $this->resource->total,
            'items_count' => isset($this->resource->lines_count)
                ? $this->resource->lines_count
                : ($this->resource->relationLoaded('lines') ? $this->resource->lines->count() : null),
            'status' => $this->resource->reversal === null ? 'confirmed' : 'reversed',
            'wallet' => ['id' => $this->resource->wallet->id, 'name' => $this->resource->wallet->name],
            'created_by' => $this->resource->relationLoaded('creator') ? [
                'id' => $this->resource->creator->id, 'name' => $this->resource->creator->name,
            ] : null,
            'financial_transaction' => [
                'id' => $this->resource->financialTransaction->id,
                'attachments_count' => isset($this->resource->financialTransaction->attachments_count)
                    ? $this->resource->financialTransaction->attachments_count
                    : ($this->resource->financialTransaction->relationLoaded('attachments')
                        ? $this->resource->financialTransaction->attachments->count() : null),
                'entries' => $this->resource->financialTransaction->relationLoaded('entries')
                    ? $this->resource->financialTransaction->entries->map(fn ($entry): array => [
                        'id' => $entry->id, 'wallet_id' => $entry->wallet_id, 'amount' => $entry->amount,
                    ])->all() : [],
            ],
            'inventory_transaction_id' => $this->resource->inventory_transaction_id,
            'inventory_transaction' => $this->resource->relationLoaded('inventoryTransaction') && $this->resource->inventoryTransaction !== null ? [
                'id' => $this->resource->inventoryTransaction->id,
                'effective_date' => $this->resource->inventoryTransaction->effective_date->toDateString(),
                'movements' => $this->resource->inventoryTransaction->relationLoaded('movements')
                    ? $this->resource->inventoryTransaction->movements->map(fn ($movement): array => [
                        'id' => $movement->id, 'inventory_item_id' => $movement->inventory_item_id,
                        'quantity' => $movement->quantity,
                    ])->all() : [],
            ] : null,
            'lines' => $this->whenLoaded('lines', fn () => $this->resource->lines->map(fn ($line): array => [
                'id' => $line->id, 'inventory_item_id' => $line->inventory_item_id,
                'item_name' => $line->item_name, 'category_name' => $line->category_name,
                'tracks_stock' => $line->tracks_stock, 'quantity' => $line->quantity,
                'input_unit_id' => $line->input_unit_id, 'input_unit_code' => $line->input_unit_code,
                'purchase_presentation_id' => $line->purchase_presentation_id,
                'presentation_name' => $line->presentation_name,
                'presentation_content_quantity' => $line->presentation_content_quantity,
                'presentation_unit_code' => $line->presentation_unit_code,
                'base_quantity' => $line->base_quantity, 'base_unit_code' => $line->base_unit_code,
                'price_mode' => $line->price_mode->value, 'price_amount' => $line->price_amount,
                'line_total' => $line->line_total, 'stock_movement_id' => $line->stock_movement_id,
                'inventory_cost_layer_id' => $line->inventory_cost_layer_id,
            ])->all()),
            'reversal' => $this->resource->reversal === null ? null : [
                'id' => $this->resource->reversal->id,
                'effective_date' => $this->resource->reversal->effective_date->toDateString(),
                'description' => $this->resource->reversal->description,
                'financial_transaction_id' => $this->resource->reversal->financial_transaction_id,
                'inventory_transaction_id' => $this->resource->reversal->inventory_transaction_id,
            ],
        ];
    }
}
