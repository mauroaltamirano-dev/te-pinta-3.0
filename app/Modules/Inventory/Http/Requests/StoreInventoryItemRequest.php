<?php

namespace App\Modules\Inventory\Http\Requests;

use App\Modules\Inventory\Enums\UnitFamily;
use App\Modules\Inventory\Models\InventoryItem;
use App\Modules\Inventory\Models\InventoryUnit;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

final class StoreInventoryItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', InventoryItem::class) === true;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'category_id' => [
                'required',
                'integer',
                Rule::exists('inventory_categories', 'id')->where('is_active', true),
            ],
            'name' => ['required', 'string', 'max:160', Rule::unique('inventory_items', 'name')],
            'tracks_stock' => ['required', 'boolean'],
            'unit_family' => ['required', Rule::enum(UnitFamily::class)],
            'base_unit_id' => ['required', 'integer', 'exists:inventory_units,id'],
            'default_input_unit_id' => ['required', 'integer', 'exists:inventory_units,id'],
            'quantity_precision' => ['required', 'integer', 'between:0,6'],
            'is_active' => ['required', 'boolean'],
            'kind' => ['required', Rule::in(['raw_material', 'semi_finished', 'finished', 'packaging', 'consumable', 'other'])],
            'recipe_input_enabled' => ['required', 'boolean'],
            'production_output_enabled' => ['required', 'boolean'],
            'order_consumption_enabled' => ['required', 'boolean'],
            'reference_mass_grams_per_unit' => ['nullable', 'decimal:0,6', 'gt:0'],
        ];
    }

    /** @return array<int, callable> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->hasAny(['unit_family', 'base_unit_id', 'default_input_unit_id'])) {
                return;
            }

            $family = $this->string('unit_family')->toString();
            $baseUnit = InventoryUnit::query()->find($this->integer('base_unit_id'));
            $defaultUnit = InventoryUnit::query()->find($this->integer('default_input_unit_id'));

            if ($baseUnit !== null && (! $baseUnit->is_base || $baseUnit->family->value !== $family)) {
                $validator->errors()->add('base_unit_id', 'La unidad base debe ser la unidad base de la familia seleccionada.');
            }

            if ($defaultUnit !== null && $defaultUnit->family->value !== $family) {
                $validator->errors()->add('default_input_unit_id', 'La unidad de ingreso debe pertenecer a la familia seleccionada.');
            }
            if ($this->filled('reference_mass_grams_per_unit') && $family !== UnitFamily::Count->value) {
                $validator->errors()->add('reference_mass_grams_per_unit', 'El peso de referencia sólo se aplica a unidades contables.');
            }
        }];
    }
}
