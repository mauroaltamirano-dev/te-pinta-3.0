<?php

namespace App\Modules\Management\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class ReversePurchaseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('reverse', $this->route('purchase')) === true;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return ['effective_date' => ['required', 'date_format:Y-m-d'], 'description' => ['required', 'string', 'max:240']];
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['description' => trim($this->string('description')->toString())]);
    }
}
