<?php

namespace App\Modules\Instagram\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class UpdateInstagramStoryAssetRequest extends FormRequest
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
            'category' => ['required', 'string', 'max:64', 'regex:/^[a-z0-9_-]+$/'],
            'image' => ['nullable', 'file', 'mimetypes:image/jpeg,image/png', 'mimes:jpg,jpeg,png', 'extensions:jpg,jpeg,png', 'max:8192', 'dimensions:min_width=1,min_height=1,max_width=8192,max_height=8192'],
            'is_active' => ['required', 'boolean'],
        ];
    }
}
