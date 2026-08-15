<?php

namespace App\Modules\Customers\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class RestoreCustomerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('restore', $this->route('customer')) === true;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [];
    }
}
