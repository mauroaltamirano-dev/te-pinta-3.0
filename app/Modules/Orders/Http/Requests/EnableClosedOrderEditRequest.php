<?php

namespace App\Modules\Orders\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class EnableClosedOrderEditRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('order')) === true;
    }

    /** @return array<string, array<int, string>> */
    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', 'min:3', 'max:300'],
            'expected_version' => ['required', 'integer', 'min:1'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $reason = $this->input('reason');
        $this->merge(['reason' => is_string($reason) ? trim($reason) : $reason]);
    }
}
