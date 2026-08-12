<?php

namespace App\Modules\Customers\Http\Requests;

use App\Modules\Customers\Support\PhoneNormalizer;
use Illuminate\Validation\Rule;

trait StoresCustomerData
{
    /** @return array<string, array<int, mixed>> */
    protected function customerRules(?int $ignoreCustomerId = null): array
    {
        $uniquePhone = Rule::unique('customers', 'phone_normalized')
            ->whereNull('deleted_at');

        if ($ignoreCustomerId !== null) {
            $uniquePhone->ignore($ignoreCustomerId);
        }

        return [
            'name' => ['required', 'string', 'max:160'],
            'phone' => ['nullable', 'string', 'max:40'],
            'phone_normalized' => ['nullable', 'string', 'max:40', $uniquePhone],
            'address' => ['nullable', 'string', 'max:1000'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ];
    }

    protected function normalizeCustomerInput(): void
    {
        $phone = $this->input('phone');
        $phone = is_string($phone) && trim($phone) !== '' ? trim($phone) : null;

        $this->merge([
            'name' => is_string($this->input('name')) ? trim($this->input('name')) : $this->input('name'),
            'phone' => $phone,
            'phone_normalized' => app(PhoneNormalizer::class)->normalize($phone),
            'address' => $this->nullableTrimmedString('address'),
            'notes' => $this->nullableTrimmedString('notes'),
        ]);
    }

    private function nullableTrimmedString(string $key): mixed
    {
        $value = $this->input($key);

        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }
}
