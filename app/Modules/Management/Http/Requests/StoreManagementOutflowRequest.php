<?php

namespace App\Modules\Management\Http\Requests;

use App\Modules\Management\Enums\ManagementOutflowType;
use App\Modules\Management\Models\ManagementOutflow;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class StoreManagementOutflowRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', ManagementOutflow::class) === true;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'type' => ['required', Rule::enum(ManagementOutflowType::class)],
            'category_id' => ['required', 'integer', Rule::exists('management_outflow_categories', 'id')],
            'wallet_id' => ['required', 'integer', Rule::exists('wallets', 'id')],
            'description' => ['required', 'string', 'max:240'],
            'counterparty' => ['nullable', 'string', 'max:160'],
            'amount' => ['required', 'integer', 'min:1', 'max:999999999999'],
            'effective_date' => ['required', 'date_format:Y-m-d'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'description' => trim($this->string('description')->toString()),
            'counterparty' => trim($this->string('counterparty')->toString()) ?: null,
        ]);
    }
}
