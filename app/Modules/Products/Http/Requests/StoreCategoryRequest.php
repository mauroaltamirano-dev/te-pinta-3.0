<?php

namespace App\Modules\Products\Http\Requests;

use App\Modules\Products\Models\Category;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class StoreCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Category::class) === true;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100', Rule::unique('categories', 'name')->withoutTrashed()],
            'sort_order' => ['required', 'integer', 'min:0', 'max:2147483647'],
            'is_active' => ['required', 'boolean'],
        ];
    }
}
