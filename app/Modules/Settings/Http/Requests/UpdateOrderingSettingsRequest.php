<?php

namespace App\Modules\Settings\Http\Requests;

use App\Modules\Identity\Enums\Permission;
use App\Modules\Identity\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class UpdateOrderingSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user instanceof User && $user->hasPermission(Permission::CatalogManage);
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        $moneyRules = ['required', 'integer', 'min:0', 'max:2147483647'];

        return [
            'empanada_unit_surcharge_percent' => ['required', 'integer', 'min:0', 'max:100'],
            'combined_dozen_discount_percent' => ['required', 'integer', 'min:0', 'max:100'],
            // Retired inputs: dozens are priced per flavor (docs/ORDERS_AND_PRICING.md); stored rows are kept.
            'combined_dozen_raw_price' => ['prohibited'],
            'combined_dozen_cooked_price' => ['prohibited'],
            'combined_half_dozen_surcharge' => ['sometimes', 'integer', 'min:0', 'max:2147483647'],
            'delivery_fee' => $moneyRules,
            'whatsapp_order_phone' => ['nullable', 'string', 'max:32', 'regex:/^\+?[0-9 ()-]*$/'],
            'raw_price_discounts' => ['required', 'array:unit,half_dozen,dozen'],
            'raw_price_discounts.unit' => ['prohibited'],
            'raw_price_discounts.half_dozen' => ['prohibited'],
            'raw_price_discounts.dozen' => ['required', 'integer', 'min:1', 'max:2147483647'],
            'addons' => ['required', 'array', 'max:50'],
            'addons.*' => ['required', 'array:id,name,price,is_available,sort_order'],
            'addons.*.id' => [
                'required',
                'integer',
                'distinct',
                Rule::exists('order_addons', 'id')->whereNull('deleted_at'),
            ],
            'addons.*.name' => ['required', 'string', 'max:120'],
            'addons.*.price' => $moneyRules,
            'addons.*.is_available' => ['required', 'boolean'],
            'addons.*.sort_order' => ['required', 'integer', 'min:0', 'max:2147483647'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        $retiredCombined = 'El precio fijo de docena combinada ya no se usa; recargá la página.';
        $retiredDiscount = 'Solo se usa el descuento crudo de docena; recargá la página.';

        return [
            'combined_dozen_raw_price.prohibited' => $retiredCombined,
            'combined_dozen_cooked_price.prohibited' => $retiredCombined,
            'raw_price_discounts.unit.prohibited' => $retiredDiscount,
            'raw_price_discounts.half_dozen.prohibited' => $retiredDiscount,
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'whatsapp_order_phone' => $this->input('whatsapp_order_phone') ?: null,
        ]);
    }
}
