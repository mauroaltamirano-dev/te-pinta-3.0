<?php

namespace App\Modules\Management\Http\Requests;

use App\Modules\Management\Enums\PurchasePriceMode;
use App\Modules\Management\Models\Purchase;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

final class StorePurchaseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Purchase::class) === true;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'idempotency_key' => ['required', 'uuid'],
            'wallet_id' => ['required', 'integer', Rule::exists('wallets', 'id')],
            'supplier_name' => ['nullable', 'string', 'max:160'],
            'description' => ['nullable', 'string', 'max:240'],
            'effective_date' => ['required', 'date_format:Y-m-d'],
            'shipping_amount' => ['nullable', 'integer', 'min:0', 'max:999999999999'],
            'lines' => ['required', 'array', 'min:1', 'max:100'],
            'lines.*.inventory_item_id' => ['required', 'integer', 'distinct', Rule::exists('inventory_items', 'id')],
            'lines.*.purchase_presentation_id' => ['nullable', 'integer', Rule::exists('inventory_purchase_presentations', 'id')],
            'lines.*.input_unit_id' => ['nullable', 'integer', Rule::exists('inventory_units', 'id')],
            'lines.*.quantity' => ['required', 'regex:/^\d{1,14}(?:\.\d{1,6})?$/'],
            'lines.*.price_mode' => ['required', Rule::enum(PurchasePriceMode::class)],
            'lines.*.price_amount' => ['required', 'integer', 'min:0', 'max:999999999999'],
        ];
    }

    /** @return array<int, callable> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            foreach ((array) $this->input('lines', []) as $index => $line) {
                if (! is_array($line)) {
                    continue;
                }
                $hasPresentation = filled($line['purchase_presentation_id'] ?? null);
                $hasUnit = filled($line['input_unit_id'] ?? null);
                if ($hasPresentation === $hasUnit) {
                    $validator->errors()->add("lines.{$index}.input_unit_id", 'Elegí una presentación o una unidad, no ambas.');
                }
            }
        }];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'supplier_name' => trim($this->string('supplier_name')->toString()) ?: null,
            'description' => trim($this->string('description')->toString()) ?: null,
            'shipping_amount' => $this->input('shipping_amount', 0),
        ]);
    }
}
