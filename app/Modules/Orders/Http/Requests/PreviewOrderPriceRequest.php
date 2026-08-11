<?php

namespace App\Modules\Orders\Http\Requests;

use App\Modules\Orders\Enums\PricingMode;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class PreviewOrderPriceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->is_admin === true;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'items' => ['required', 'array', 'min:1', 'max:100'],
            'items.*.product_id' => [
                'required',
                'integer',
                'distinct',
                Rule::exists('products', 'id')->whereNull('deleted_at'),
            ],
            'items.*.pricing_mode' => ['required', Rule::enum(PricingMode::class)],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:100'],
            'items.*.is_cooked' => ['sometimes', 'boolean'],
        ];
    }
}
