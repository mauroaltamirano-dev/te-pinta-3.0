<?php

namespace App\Modules\Orders\Http\Requests;

use App\Modules\Customers\Support\PhoneNormalizer;
use App\Modules\Orders\Enums\DeliveryPeriod;
use App\Modules\Orders\Enums\DeliveryType;
use App\Modules\Orders\Enums\DiscountType;
use App\Modules\Orders\Enums\OrderCondition;
use App\Modules\Orders\Enums\PaymentMethod;
use App\Modules\Orders\Enums\PaymentStatus;
use Illuminate\Validation\Rule;

trait ValidatesOrderCheckout
{
    /** @return array<string, array<int, mixed>> */
    protected function checkoutRules(bool $public, bool $dineIn = false): array
    {
        $excludeNestedCustomer = ! $public && $this->filled('customer_id');

        return [
            'items' => ['required', 'array', 'min:1', 'max:50'],
            'items.*' => ['required', 'array:product_id,quantity'],
            'items.*.product_id' => ['required', 'integer', 'distinct'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:120'],
            'addons' => ['sometimes', 'array', 'max:20'],
            'addons.*' => ['required', 'array:addon_id,quantity'],
            'addons.*.addon_id' => ['required', 'integer', 'distinct'],
            'addons.*.quantity' => ['required', 'integer', 'min:1', 'max:100'],
            'condition' => ['required', Rule::enum(OrderCondition::class)],
            'delivery_type' => ['required', Rule::in($dineIn ? [DeliveryType::DineIn->value] : [DeliveryType::Delivery->value, DeliveryType::Pickup->value])],
            'delivery_period' => [$dineIn ? 'prohibited' : 'required', 'nullable', Rule::enum(DeliveryPeriod::class)],
            'delivery_date' => [
                $dineIn ? 'prohibited' : 'required',
                'nullable',
                'date_format:Y-m-d',
            ],
            'delivery_time' => [$dineIn ? 'prohibited' : 'nullable', 'nullable', 'date_format:H:i'],
            'delivery_address' => [$dineIn ? 'prohibited' : 'nullable', 'nullable', 'required_if:delivery_type,delivery', 'string', 'max:1000'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'customer_id' => $public ? ['prohibited'] : ['nullable', 'integer', Rule::exists('customers', 'id')->whereNull('deleted_at')],
            'customer' => [Rule::excludeIf($excludeNestedCustomer), $dineIn ? 'nullable' : 'required_without:customer_id', 'array:name,phone,phone_normalized,address'],
            'customer.name' => [Rule::excludeIf($excludeNestedCustomer), $dineIn ? 'nullable' : 'required_without:customer_id', 'nullable', 'string', 'max:160'],
            'customer.phone' => [Rule::excludeIf($excludeNestedCustomer), $public ? 'required' : 'nullable', 'string', 'max:40'],
            'customer.phone_normalized' => [Rule::excludeIf($excludeNestedCustomer), $public ? 'required' : 'nullable', 'string', 'max:40', ...($public || $dineIn ? [] : [Rule::unique('customers', 'phone_normalized')->whereNull('deleted_at')])],
            'customer.address' => [Rule::excludeIf($excludeNestedCustomer), $dineIn ? 'prohibited' : 'nullable', 'nullable', 'string', 'max:1000'],
            'payment_status' => $public ? ['prohibited'] : ['sometimes', Rule::enum(PaymentStatus::class)],
            'payment_method' => $public ? ['prohibited'] : [
                'nullable',
                Rule::enum(PaymentMethod::class),
                Rule::requiredIf(fn (): bool => $this->input('payment_status', PaymentStatus::Unpaid->value) === PaymentStatus::Paid->value),
                Rule::prohibitedIf(fn (): bool => $this->input('payment_status', PaymentStatus::Unpaid->value) !== PaymentStatus::Paid->value),
            ],
            'discount_type' => $public ? ['prohibited'] : ['nullable', Rule::enum(DiscountType::class), 'required_with:discount_value'],
            'discount_value' => $public ? ['prohibited'] : [
                'nullable', 'integer', 'min:0', 'required_with:discount_type',
                Rule::when($this->input('discount_type') === DiscountType::Percentage->value, ['max:100'], ['max:999999999']),
            ],
            'source' => ['prohibited'],
            'status' => ['prohibited'],
            'total' => ['prohibited'],
            'shipping_fee' => ['prohibited'],
            'combined_price' => ['prohibited'],
            'discount_amount' => ['prohibited'],
        ];
    }

    protected function normalizeCheckoutInput(): void
    {
        $customer = $this->input('customer');
        if (is_array($customer)) {
            $phone = isset($customer['phone']) && is_string($customer['phone']) ? trim($customer['phone']) : null;
            $customer['name'] = isset($customer['name']) && is_string($customer['name']) ? trim($customer['name']) : null;
            $customer['phone'] = $phone === '' ? null : $phone;
            $customer['phone_normalized'] = app(PhoneNormalizer::class)->normalize($customer['phone']);
            $customer['address'] = isset($customer['address']) && is_string($customer['address']) && trim($customer['address']) !== '' ? trim($customer['address']) : null;
            $this->merge(['customer' => $customer]);
        }
        foreach (['delivery_address', 'notes'] as $key) {
            $value = $this->input($key);
            $this->merge([$key => is_string($value) && trim($value) !== '' ? trim($value) : null]);
        }
    }
}
