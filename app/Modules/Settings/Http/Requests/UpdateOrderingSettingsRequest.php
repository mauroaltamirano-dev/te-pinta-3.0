<?php

namespace App\Modules\Settings\Http\Requests;

use App\Modules\Identity\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class UpdateOrderingSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user instanceof User && $user->is_admin;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        $moneyRules = ['required', 'integer', 'min:0', 'max:2147483647'];

        return [
            'combined_dozen_raw_price' => [...$moneyRules, 'lt:combined_dozen_cooked_price'],
            'combined_dozen_cooked_price' => $moneyRules,
            'combined_half_dozen_surcharge' => ['sometimes', 'integer', 'min:0', 'max:2147483647'],
            'delivery_fee' => $moneyRules,
            'whatsapp_order_phone' => ['nullable', 'string', 'max:32', 'regex:/^\+?[0-9 ()-]*$/'],
            'raw_price_discounts' => ['required', 'array:unit,half_dozen,dozen'],
            'raw_price_discounts.unit' => ['required', 'integer', 'min:1', 'max:2147483647'],
            'raw_price_discounts.half_dozen' => ['required', 'integer', 'min:1', 'max:2147483647'],
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

    protected function prepareForValidation(): void
    {
        $this->merge([
            'whatsapp_order_phone' => $this->input('whatsapp_order_phone') ?: null,
        ]);
    }
}
