<?php

namespace App\Modules\Products\Http\Requests;

use App\Modules\Products\Enums\PriceTier;
use App\Modules\Products\Enums\ProductPricingFamily;
use App\Modules\Products\Models\Product;
use App\Modules\Products\Rules\CookedPriceSupportsRawDiscount;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class StoreProductRequest extends FormRequest
{
    use NormalizesHomogeneousSurcharge;

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
        return ['unit_price.prohibited' => 'El precio por unidad de las empanadas se calcula desde la docena.'];
    }

    protected function prepareForValidation(): void
    {
        $this->normalizeHomogeneousSurcharge();
        $slug = $this->input('slug');

        $this->merge([
            'slug' => $slug === '' ? null : $slug,
            'description' => $this->input('description') ?: null,
            'long_description' => $this->input('long_description') ?: null,
            'public_ingredients' => $this->input('public_ingredients') ?: null,
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
            'tags' => ['sometimes', 'array', 'max:50'],
            'tags.*' => ['required', 'integer', 'distinct', 'exists:tags,id'],
            'pricing_family' => ['required', Rule::enum(ProductPricingFamily::class)],
            // The empanada unit price is derived from its dozen (EmpanadaUnitPrice) and never accepted from the client.
            'unit_price' => [Rule::requiredIf(! $isEmpanada), Rule::prohibitedIf($isEmpanada), 'nullable', ...$money],
            'half_dozen_price' => [Rule::requiredIf($isEmpanada), Rule::prohibitedIf($isUnit), 'nullable', ...$money],
            'dozen_price' => [Rule::requiredIf($isEmpanada), Rule::prohibitedIf($isUnit), 'nullable', ...$money, ...($isEmpanada ? [new CookedPriceSupportsRawDiscount(PriceTier::Dozen)] : [])],
            'is_available' => ['required', 'boolean'],
            'sort_order' => ['required', 'integer', 'min:0', 'max:2147483647'],
        ];
    }
}
