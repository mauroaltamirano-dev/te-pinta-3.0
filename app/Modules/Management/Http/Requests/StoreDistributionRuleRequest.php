<?php

namespace App\Modules\Management\Http\Requests;

use App\Modules\Management\Models\DistributionRule;
use Illuminate\Foundation\Http\FormRequest;

final class StoreDistributionRuleRequest extends FormRequest
{
    use StoresDistributionAllocations;

    public function authorize(): bool
    {
        return $this->user()?->can('create', DistributionRule::class) === true;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return ['name' => ['required', 'string', 'max:120'], ...$this->allocationRules()];
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['name' => trim($this->string('name')->toString())]);
    }
}
