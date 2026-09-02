<?php

namespace App\Modules\Management\Http\Requests;

use App\Modules\Management\Models\ManagementDistribution;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class StoreManagementDistributionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', ManagementDistribution::class) === true;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'source_wallet_id' => ['required', 'integer', Rule::exists('wallets', 'id')->whereNull('archived_at')],
            'rule_version_id' => ['required', 'integer', Rule::exists('management_distribution_rule_versions', 'id')],
            'amount' => ['required', 'integer', 'min:1', 'max:999999999999'],
            'effective_date' => ['required', 'date_format:Y-m-d'],
            'description' => ['nullable', 'string', 'max:240'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['description' => trim($this->string('description')->toString()) ?: null]);
    }
}
