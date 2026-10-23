<?php

namespace App\Modules\Products\Http\Requests;

use App\Modules\Products\Enums\PriceTier;
use App\Modules\Products\Enums\ProductPricingFamily;
use App\Modules\Products\Models\Product;
use App\Modules\Products\Rules\AssignableSubcategory;
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

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'unit_price.prohibited' => 'El precio por unidad de las empanadas se calcula desde la docena.',
            'half_dozen_price.prohibited' => 'La media docena de las empanadas se calcula como 6 unidades.',
            'homogeneous_half_dozen_surcharge.prohibited' => 'El recargo de media docena ya no se usa; recargá la página.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $slug = $this->input('slug');

        $this->merge([
            'slug' => $slug === '' ? null : $slug,
            'description' => $this->input('description') ?: null,
            'long_description' => $this->input('long_description') ?: null,
            'public_ingredients' => $this->input('public_ingredients') ?: null,
            'subcategory_id' => $this->input('subcategory_id') ?: null,
            'tags' => $this->input('tags', []),
        ]);
    }

    /** @return array<string, array<int, mixed>> */
    private function productRules(): array
    {
        $isEmpanada = $this->input('pricing_family') === ProductPricingFamily::Empanada->value;
        $isUnit = $this->input('pricing_family') === ProductPricingFamily::Unit->value;
        $money = ['integer', 'min:0', 'max:2147483647'];

        return [
            'name' => ['required', 'string', 'max:160'],
            'slug' => ['nullable', 'string', 'max:255', 'regex:/\A[a-z0-9]+(?:-[a-z0-9]+)*\z/', Rule::unique('products', 'slug')],
            'description' => ['nullable', 'string', 'max:500'],
            'long_description' => ['nullable', 'string', 'max:5000'],
            'public_ingredients' => ['nullable', 'string', 'max:2000'],
            'image' => ['nullable', 'file', 'mimetypes:image/jpeg,image/png,image/webp', 'mimes:jpg,jpeg,png,webp', 'extensions:jpg,jpeg,png,webp', 'max:4096'],
            'category_id' => ['required', 'integer', Rule::exists('categories', 'id')->whereNull('deleted_at')],
            'subcategory_id' => ['nullable', 'integer', new AssignableSubcategory($this->input('category_id'), null)],
            'tags' => ['sometimes', 'array', 'max:50'],
            'tags.*' => ['required', 'integer', 'distinct', 'exists:tags,id'],
            'pricing_family' => ['required', Rule::enum(ProductPricingFamily::class)],
            // The empanada unit price is derived from its dozen (EmpanadaUnitPrice) and never accepted from the client.
            'unit_price' => [Rule::requiredIf(! $isEmpanada), Rule::prohibitedIf($isEmpanada), 'nullable', ...$money],
            // The empanada half dozen is six derived units; a unit product stores its unit price.
            'half_dozen_price' => ['prohibited'],
            // Retired P0 input that used to set the half dozen.
            'homogeneous_half_dozen_surcharge' => ['prohibited'],
            'dozen_price' => [Rule::requiredIf($isEmpanada), Rule::prohibitedIf($isUnit), 'nullable', ...$money, ...($isEmpanada ? [new CookedPriceSupportsRawDiscount(PriceTier::Dozen)] : [])],
            'is_available' => ['required', 'boolean'],
            'sort_order' => ['required', 'integer', 'min:0', 'max:2147483647'],
        ];
    }
}
