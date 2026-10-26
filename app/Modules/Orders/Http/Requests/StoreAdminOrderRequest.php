<?php

namespace App\Modules\Orders\Http\Requests;

use App\Modules\Orders\Enums\DeliveryType;
use App\Modules\Orders\Models\Order;
use Illuminate\Foundation\Http\FormRequest;

final class StoreAdminOrderRequest extends FormRequest
{
    use ValidatesOrderCheckout;

    public function authorize(): bool
    {
        return $this->user()?->can('create', Order::class) === true;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return $this->checkoutRules(false, $this->isCounterOrder() && ! $this->isQuickOrder(), $this->isQuickOrder());
    }

    /** "Pedido rápido": only products; customer and schedule are completed before confirming. */
    public function isQuickOrder(): bool
    {
        return $this->boolean('quick');
    }

    /** "Consumo en el local / mostrador": immediate, without schedule, address or required customer. */
    public function isCounterOrder(): bool
    {
        return $this->input('delivery_type') === DeliveryType::DineIn->value;
    }

    protected function prepareForValidation(): void
    {
        $this->normalizeCheckoutInput();
    }
}
