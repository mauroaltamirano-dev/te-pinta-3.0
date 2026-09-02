<?php

namespace App\Modules\Instagram\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreInstagramStoryScheduleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->is_admin === true;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:160'],
            'time' => ['required', 'date_format:H:i'],
            'days_of_week' => ['required', 'array', 'min:1', 'max:7'],
            'days_of_week.*' => ['required', 'integer', 'distinct', 'between:1,7'],
            'sort_order' => ['sometimes', 'integer', 'between:1,999'],
            'asset_id' => ['nullable', 'integer', Rule::exists('instagram_story_assets', 'id')->where('is_active', true)],
            'is_active' => ['required', 'boolean'],
        ];
    }
}
