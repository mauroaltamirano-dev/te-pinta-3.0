<?php

namespace App\Modules\Products\Http\Requests;

use App\Modules\Products\Models\Product;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class StoreProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Product::class) === true;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return $this->productRules();
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'description' => $this->input('description') ?: null,
            'tags' => $this->input('tags', []),
        ]);
    }

    /** @return array<string, array<int, mixed>> */
    private function productRules(): array
    {
        return [
            'name' => ['required', 'string', 'max:160'],
            'description' => ['nullable', 'string', 'max:500'],
            'image' => ['nullable', 'file', 'mimetypes:image/jpeg,image/png,image/webp', 'mimes:jpg,jpeg,png,webp', 'extensions:jpg,jpeg,png,webp', 'max:4096'],
            'category_id' => ['required', 'integer', Rule::exists('categories', 'id')->whereNull('deleted_at')],
            'tags' => ['sometimes', 'array', 'max:50'],
            'tags.*' => ['required', 'integer', 'distinct', 'exists:tags,id'],
            'unit_price' => ['required', 'integer', 'min:0', 'max:2147483647'],
            'half_dozen_price' => ['required', 'integer', 'min:0', 'max:2147483647'],
            'dozen_price' => ['required', 'integer', 'min:0', 'max:2147483647'],
            'is_available' => ['required', 'boolean'],
            'sort_order' => ['required', 'integer', 'min:0', 'max:2147483647'],
        ];
    }
}
