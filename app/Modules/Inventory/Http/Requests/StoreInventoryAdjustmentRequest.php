<?php

namespace App\Modules\Inventory\Http\Requests;

use App\Modules\Inventory\Models\InventoryItem;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class StoreInventoryAdjustmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', InventoryItem::class) ?? false;
    }

    /** @return array<string,list<mixed>> */
    public function rules(): array
    {
        return ['inventory_item_id' => ['required', 'integer', 'exists:inventory_items,id'], 'unit_id' => ['required', 'integer', 'exists:inventory_units,id'], 'type' => ['required', Rule::in(['count_correction', 'waste', 'damage', 'expiry', 'manual_adjustment'])], 'quantity' => ['required', 'decimal:0,6'], 'effective_date' => ['required', 'date_format:Y-m-d'], 'idempotency_key' => ['required', 'uuid'], 'explicit_total_cost' => ['nullable', 'integer', 'min:0'], 'note' => ['nullable', 'string', 'max:240'], 'delta' => ['prohibited'], 'stock_before' => ['prohibited'], 'cost_total' => ['prohibited'], 'cost_coverage' => ['prohibited']];
    }
}
