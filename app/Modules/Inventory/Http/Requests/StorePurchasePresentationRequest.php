<?php

namespace App\Modules\Inventory\Http\Requests;

use App\Modules\Inventory\Models\InventoryItem;
use App\Modules\Inventory\Models\InventoryUnit;
use App\Modules\Inventory\Models\PurchasePresentation;
use App\Modules\Inventory\Support\UnitConverter;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Validator;

final class StorePurchasePresentationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', PurchasePresentation::class) === true;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'inventory_item_id' => [
                'required',
                'integer',
                Rule::exists('inventory_items', 'id')->where('is_active', true),
            ],
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
                $validator->errors()->add('content_unit_id', 'La unidad de contenido debe pertenecer a la familia del item.');

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
