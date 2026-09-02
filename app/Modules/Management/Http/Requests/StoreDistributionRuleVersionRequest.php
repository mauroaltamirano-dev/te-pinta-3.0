<?php

namespace App\Modules\Management\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class StoreDistributionRuleVersionRequest extends FormRequest
{
    use StoresDistributionAllocations;

    public function authorize(): bool
    {
        $rule = $this->route('distributionRule');

        return $rule !== null && $this->user()?->can('createVersion', $rule) === true;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return $this->allocationRules();
    }
}
