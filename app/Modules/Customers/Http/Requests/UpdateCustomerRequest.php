<?php

namespace App\Modules\Customers\Http\Requests;

use App\Modules\Customers\Models\Customer;
use Illuminate\Foundation\Http\FormRequest;

final class UpdateCustomerRequest extends FormRequest
{
    use StoresCustomerData;

    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('customer')) === true;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        /** @var Customer $customer */
        $customer = $this->route('customer');

        return $this->customerRules($customer->id);
    }

    protected function prepareForValidation(): void
    {
        $this->normalizeCustomerInput();
    }
}
