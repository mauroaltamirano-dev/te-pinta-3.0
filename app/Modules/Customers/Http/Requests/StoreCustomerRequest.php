<?php

namespace App\Modules\Customers\Http\Requests;

use App\Modules\Customers\Models\Customer;
use Illuminate\Foundation\Http\FormRequest;

final class StoreCustomerRequest extends FormRequest
{
    use StoresCustomerData;

    public function authorize(): bool
    {
        return $this->user()?->can('create', Customer::class) === true;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return $this->customerRules();
    }

    protected function prepareForValidation(): void
    {
        $this->normalizeCustomerInput();
    }
}
