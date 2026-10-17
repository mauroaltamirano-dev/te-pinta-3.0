<?php

namespace App\Modules\Inventory\Http\Requests;

use App\Modules\Identity\Enums\Permission;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class ConfigureProductConsumptionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can(Permission::InventoryManage->value) ?? false;
    }

    /** @return array<string,list<mixed>> */
    public function rules(): array
    {
        return ['mode' => ['required', Rule::in(['consume', 'no_inventory'])], 'components' => ['present', 'array', 'max:100'], 'components.*.inventory_item_id' => ['required', 'integer', 'exists:inventory_items,id', 'distinct'], 'components.*.unit_id' => ['required', 'integer', 'exists:inventory_units,id'], 'components.*.quantity' => ['required', 'string', 'decimal:0,6', 'gt:0']];
    }
}
