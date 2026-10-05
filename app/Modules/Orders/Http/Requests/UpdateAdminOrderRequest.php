<?php

namespace App\Modules\Orders\Http\Requests;

use App\Modules\Orders\Enums\DeliveryType;
use App\Modules\Orders\Enums\OrderSource;
use App\Modules\Orders\Enums\OrderStatus;
use App\Modules\Orders\Models\Order;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class UpdateAdminOrderRequest extends FormRequest
{
    use ValidatesOrderCheckout;

    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('order')) === true;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        $order = $this->route('order');
        $dineIn = $order instanceof Order && $order->source === OrderSource::Pos && $order->delivery_type === DeliveryType::DineIn;
        $rules = $this->checkoutRules(false, $dineIn);
        $rules['status'] = ['required', Rule::enum(OrderStatus::class)];
        $rules['expected_version'] = ['required', 'integer', 'min:1'];
        foreach (['id', 'public_id', 'created_at', 'updated_at', 'edit_version', 'revision', 'subtotal', 'unit_price', 'items_subtotal', 'toppings_subtotal', 'cooking_surcharge', 'discount_amount'] as $field) {
            $rules[$field] = ['prohibited'];
        }

        return $rules;
    }

    protected function prepareForValidation(): void
    {
        $this->normalizeCheckoutInput();
    }
}
