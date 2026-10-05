<?php

namespace App\Modules\Orders\Http\Requests;

use App\Modules\Orders\Enums\DeliveryPeriod;
use App\Modules\Orders\Enums\DeliveryType;
use App\Modules\Orders\Enums\OrderCondition;
use App\Modules\Orders\Support\OrderBusinessDate;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class StorePublicOrderRequest extends FormRequest
{
    use ValidatesOrderCheckout;

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
            'delivery_type' => ['required', Rule::in([DeliveryType::Delivery->value, DeliveryType::Pickup->value])],
            'delivery_period' => ['required', Rule::enum(DeliveryPeriod::class)],
            'delivery_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:'.app(OrderBusinessDate::class)->todayString()],
            'delivery_time' => ['nullable', 'date_format:H:i'],
            'delivery_address' => ['nullable', 'required_if:delivery_type,delivery', 'string', 'max:1000'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'customer' => ['required', 'array:name,phone,phone_normalized,address'],
            'customer.name' => ['required', 'string', 'max:160'],
            'customer.phone' => ['required', 'string', 'max:40'],
            'customer.phone_normalized' => ['required', 'string', 'max:40'],
            'customer.address' => ['nullable', 'string', 'max:1000'],
            'idempotency_key' => ['required', 'string', 'min:16', 'max:100'],
            'customer_id' => ['prohibited'], 'payment_status' => ['prohibited'], 'payment_method' => ['prohibited'], 'status' => ['prohibited'],
            'discount_type' => ['prohibited'], 'discount_value' => ['prohibited'], 'discount_amount' => ['prohibited'],
            'source' => ['prohibited'],
            'total' => ['prohibited'], 'shipping_fee' => ['prohibited'], 'combined_price' => ['prohibited'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'delivery_date.after_or_equal' => 'La fecha de entrega debe ser igual o posterior al '.app(OrderBusinessDate::class)->today()->format('d/m/Y').'.',
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'delivery_date' => 'fecha de entrega',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->normalizeCheckoutInput();
    }
}
