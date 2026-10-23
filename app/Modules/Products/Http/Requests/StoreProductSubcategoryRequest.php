<?php

namespace App\Modules\Products\Http\Requests;

use App\Modules\Products\Models\Category;
use App\Modules\Products\Rules\UniqueSubcategoryName;
use Illuminate\Foundation\Http\FormRequest;

final class StoreProductSubcategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->category()) === true;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100', new UniqueSubcategoryName($this->category()->id)],
            'sort_order' => ['required', 'integer', 'min:0', 'max:2147483647'],
            'is_active' => ['required', 'boolean'],
        ];
    }

    private function category(): Category
    {
        /** @var Category */
        return $this->route('category');
    }
}
