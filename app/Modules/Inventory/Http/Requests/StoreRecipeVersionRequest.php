<?php

namespace App\Modules\Inventory\Http\Requests;

use App\Modules\Inventory\Models\InventoryItem;
use Illuminate\Foundation\Http\FormRequest;

final class StoreRecipeVersionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', InventoryItem::class) ?? false;
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return ['output_inventory_item_id' => ['required', 'integer', 'exists:inventory_items,id'], 'output_unit_id' => ['required', 'integer', 'exists:inventory_units,id'], 'expected_output_quantity' => ['required', 'decimal:0,6', 'gt:0'], 'ingredients' => ['required', 'array', 'min:1'], 'ingredients.*.inventory_item_id' => ['required', 'integer', 'exists:inventory_items,id'], 'ingredients.*.unit_id' => ['required', 'integer', 'exists:inventory_units,id'], 'ingredients.*.quantity' => ['required', 'decimal:0,6', 'gt:0']];
    }
}
