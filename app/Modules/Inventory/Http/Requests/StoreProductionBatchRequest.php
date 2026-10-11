<?php

namespace App\Modules\Inventory\Http\Requests;

use App\Modules\Inventory\Models\InventoryItem;
use Illuminate\Foundation\Http\FormRequest;

final class StoreProductionBatchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', InventoryItem::class) ?? false;
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return ['recipe_version_id' => ['required', 'integer', 'exists:inventory_recipe_versions,id'], 'output_unit_id' => ['required', 'integer', 'exists:inventory_units,id'], 'actual_output_quantity' => ['required', 'decimal:0,6', 'gt:0'], 'effective_date' => ['required', 'date'], 'idempotency_key' => ['required', 'uuid'], 'notes' => ['nullable', 'string', 'max:240']];
    }
}
