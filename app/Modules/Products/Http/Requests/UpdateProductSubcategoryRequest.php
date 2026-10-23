<?php

namespace App\Modules\Products\Http\Requests;

use App\Modules\Products\Models\ProductSubcategory;
use App\Modules\Products\Rules\UniqueSubcategoryName;
use Illuminate\Foundation\Http\FormRequest;

final class UpdateProductSubcategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->subcategory()->category) === true;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        $subcategory = $this->subcategory();

        return [
            'name' => ['required', 'string', 'max:100', new UniqueSubcategoryName($subcategory->category_id, $subcategory->id)],
            'sort_order' => ['required', 'integer', 'min:0', 'max:2147483647'],
            'is_active' => ['required', 'boolean'],
        ];
    }

    private function subcategory(): ProductSubcategory
    {
        /** @var ProductSubcategory */
        return $this->route('subcategory');
    }
}
