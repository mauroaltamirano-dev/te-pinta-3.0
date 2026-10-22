<?php

namespace App\Modules\Inventory\Http\Requests;

use App\Modules\Inventory\Models\InventoryItem;
use App\Modules\Inventory\Models\InventoryUnit;
use App\Modules\Inventory\Support\UnitConverter;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Validator;

final class UpdatePurchasePresentationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('purchasePresentation')) === true;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'inventory_item_id' => ['required', 'integer', 'exists:inventory_items,id'],
            'name' => ['required', 'string', 'max:120'],
            'content_quantity' => ['required', 'decimal:0,6', 'gt:0'],
            'content_unit_id' => ['required', 'integer', 'exists:inventory_units,id'],
            'is_active' => ['required', 'boolean'],
        ];
    }

    /** @return array<int, callable> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->hasAny(['inventory_item_id', 'content_unit_id'])) {
                return;
            }

            $item = InventoryItem::query()->find($this->integer('inventory_item_id'));
            $unit = InventoryUnit::query()->find($this->integer('content_unit_id'));

            if ($item === null || $unit === null) {
                return;
            }

            if ($item->unit_family !== $unit->family) {
                $validator->errors()->add('content_unit_id', 'La unidad del contenido no corresponde a la medida del insumo. Elegí una unidad de la misma medida.');

                return;
            }

            try {
                app(UnitConverter::class)->toBase($item, $unit, $this->string('content_quantity')->toString());
            } catch (ValidationException $exception) {
                $validator->errors()->add('content_quantity', (string) collect($exception->errors())->flatten()->first());
            }
        }];
    }
}
