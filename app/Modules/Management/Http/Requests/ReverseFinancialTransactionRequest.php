<?php

namespace App\Modules\Management\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class ReverseFinancialTransactionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('reverse', $this->route('financialTransaction')) === true;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return ['effective_date' => ['required', 'date_format:Y-m-d'], 'description' => ['nullable', 'string', 'max:240']];
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['description' => $this->input('description') ?: null]);
    }
}
