<?php

namespace App\Modules\Management\Http\Requests;

use App\Modules\Management\Models\Wallet;
use Illuminate\Foundation\Http\FormRequest;

final class StoreWalletRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Wallet::class) === true;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'sort_order' => ['sometimes', 'integer', 'min:0', 'max:4294967295'],
            'is_order_payment_default' => ['sometimes', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'name' => trim((string) $this->input('name')),
            'sort_order' => $this->input('sort_order', 0),
            'is_order_payment_default' => $this->boolean('is_order_payment_default'),
        ]);
    }
}
