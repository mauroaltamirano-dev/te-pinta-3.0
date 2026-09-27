<?php

namespace App\Modules\Products\Http\Requests;

use App\Modules\Products\Enums\PriceTier;
use App\Modules\Products\Models\Product;
use App\Modules\Products\Rules\CookedPriceSupportsRawDiscount;
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
            'long_description' => $this->input('long_description') ?: null,
            'public_ingredients' => $this->input('public_ingredients') ?: null,
            'tags' => $this->input('tags', []),
        ]);
    }

    /** @return array<string, array<int, mixed>> */
    private function productRules(): array
    {
        return [
            'name' => ['required', 'string', 'max:160'],
            'description' => ['nullable', 'string', 'max:500'],
            'long_description' => ['nullable', 'string', 'max:5000'],
            'public_ingredients' => ['nullable', 'string', 'max:2000'],
            'image' => ['nullable', 'file', 'mimetypes:image/jpeg,image/png,image/webp', 'mimes:jpg,jpeg,png,webp', 'extensions:jpg,jpeg,png,webp', 'max:4096'],
            'category_id' => ['required', 'integer', Rule::exists('categories', 'id')->whereNull('deleted_at')],
            'tags' => ['sometimes', 'array', 'max:50'],
            'tags.*' => ['required', 'integer', 'distinct', 'exists:tags,id'],
            'unit_price' => ['required', 'integer', 'min:0', 'max:2147483647', new CookedPriceSupportsRawDiscount(PriceTier::Unit)],
            'half_dozen_price' => ['required', 'integer', 'min:0', 'max:2147483647', new CookedPriceSupportsRawDiscount(PriceTier::HalfDozen)],
            'dozen_price' => ['required', 'integer', 'min:0', 'max:2147483647', new CookedPriceSupportsRawDiscount(PriceTier::Dozen)],
            'is_available' => ['required', 'boolean'],
            'sort_order' => ['required', 'integer', 'min:0', 'max:2147483647'],
        ];
    }
}
