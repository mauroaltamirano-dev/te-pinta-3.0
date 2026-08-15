<?php

namespace App\Modules\Orders\Http\Requests;

use App\Modules\Orders\Enums\PaymentMethod;
use App\Modules\Orders\Enums\PaymentStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class UpdateOrderPaymentStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('order')) === true;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'payment_status' => ['required', Rule::enum(PaymentStatus::class)],
            'payment_method' => [
                'nullable',
                Rule::enum(PaymentMethod::class),
                Rule::requiredIf(fn (): bool => $this->input('payment_status') === PaymentStatus::Paid->value),
                Rule::prohibitedIf(fn (): bool => $this->input('payment_status') !== PaymentStatus::Paid->value),
            ],
        ];
    }
}
