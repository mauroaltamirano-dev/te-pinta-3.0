<?php

namespace App\Modules\Orders\Http\Requests;

use App\Modules\Orders\Enums\DeliveryType;
use App\Modules\Orders\Enums\OrderCondition;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class PreviewPublicOrderPriceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'items' => ['required', 'array', 'min:1', 'max:50'],
            'items.*' => ['required', 'array:product_slug,quantity'],
            'items.*.product_slug' => ['required', 'string', 'distinct', 'max:160'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:120'],
            'addons' => ['sometimes', 'array', 'max:20'],
            'addons.*' => ['required', 'array:addon_slug,quantity'],
            'addons.*.addon_slug' => ['required', 'string', 'distinct', 'max:160'],
            'addons.*.quantity' => ['required', 'integer', 'min:1', 'max:100'],
            'condition' => ['required', Rule::enum(OrderCondition::class)],
            'delivery_type' => ['required', Rule::enum(DeliveryType::class)],
            'total' => ['prohibited'], 'shipping_fee' => ['prohibited'], 'combined_price' => ['prohibited'],
        ];
    }
}
