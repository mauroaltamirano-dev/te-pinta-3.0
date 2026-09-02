<?php

namespace App\Modules\Management\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class UpdateDistributionRuleStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        $rule = $this->route('distributionRule');

        return $rule !== null && $this->user()?->can('update', $rule) === true;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return ['active' => ['required', 'boolean']];
    }
}
