<?php

namespace App\Modules\Orders\Http\Requests;

use App\Modules\Identity\Enums\Permission;
use App\Modules\Orders\Enums\DeliveryType;
use App\Modules\Orders\Enums\DiscountType;
use App\Modules\Orders\Enums\OrderCondition;
use App\Modules\Orders\Enums\PricingMode;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class PreviewOrderPriceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->routeIs('public.*') || $this->user()?->can(Permission::OrdersManage->value) === true;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'items' => ['required', 'array', 'min:1', 'max:50'],
            'items.*' => ['required', 'array:product_id,quantity,pricing_mode,is_cooked,unit_price'],
            'items.*.product_id' => ['required', 'integer', 'distinct', Rule::exists('products', 'id')->whereNull('deleted_at')],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:120'],
            'items.*.pricing_mode' => ['nullable', Rule::enum(PricingMode::class)],
            'items.*.is_cooked' => ['sometimes', 'boolean'],
            'items.*.unit_price' => ['sometimes', 'integer'],
            'addons' => ['sometimes', 'array', 'max:20'],
            'addons.*' => ['required', 'array:addon_id,quantity'],
            'addons.*.addon_id' => ['required', 'integer', 'distinct'],
            'addons.*.quantity' => ['required', 'integer', 'min:1', 'max:100'],
            'condition' => ['nullable', Rule::enum(OrderCondition::class)],
            'delivery_type' => ['nullable', Rule::enum(DeliveryType::class)],
            'discount_type' => ['nullable', Rule::enum(DiscountType::class), 'required_with:discount_value'],
            'discount_value' => ['nullable', 'integer', 'min:0', 'required_with:discount_type', Rule::when($this->input('discount_type') === DiscountType::Percentage->value, ['max:100'], ['max:999999999'])],
            'total' => ['prohibited'], 'shipping_fee' => ['prohibited'], 'combined_price' => ['prohibited'], 'discount_amount' => ['prohibited'],
        ];
    }
}
