<?php

namespace App\Modules\Orders\Http\Requests;

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
        return $this->checkoutRules(false);
    }

    protected function prepareForValidation(): void
    {
        $this->normalizeCheckoutInput();
    }
}
