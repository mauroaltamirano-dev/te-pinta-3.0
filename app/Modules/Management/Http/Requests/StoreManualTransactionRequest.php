<?php

namespace App\Modules\Management\Http\Requests;

use App\Modules\Management\Enums\FinancialTransactionType;
use App\Modules\Management\Models\FinancialTransaction;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class StoreManualTransactionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', FinancialTransaction::class) === true;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'wallet_id' => ['required', 'integer', Rule::exists('wallets', 'id')],
            'type' => ['required', Rule::in(array_map(fn (FinancialTransactionType $type): string => $type->value, FinancialTransactionType::manualCases()))],
            'amount' => ['required', 'integer', 'min:1', 'max:999999999999'],
            'effective_date' => ['required', 'date_format:Y-m-d'],
            'description' => ['nullable', 'string', 'max:240'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['description' => $this->input('description') ?: null]);
    }
}
