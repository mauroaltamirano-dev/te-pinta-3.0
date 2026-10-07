<?php

namespace App\Modules\Inventory\Http\Requests;

use App\Modules\Inventory\Models\InventoryCategory;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class StoreInventoryCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', InventoryCategory::class) === true;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120', Rule::unique('inventory_categories', 'name')],
        ];
    }
}
