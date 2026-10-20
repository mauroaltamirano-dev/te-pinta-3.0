<?php

namespace App\Modules\Orders\Http\Requests;

use App\Modules\Orders\Enums\PaymentMethod;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class ChargeTableSessionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->canOperateCashier() === true;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return ['payment_method' => ['required', Rule::enum(PaymentMethod::class)]];
    }
}
