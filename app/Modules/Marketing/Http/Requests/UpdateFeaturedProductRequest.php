<?php

namespace App\Modules\Marketing\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class UpdateFeaturedProductRequest extends FormRequest
{
    use ValidatesFeaturedProduct;

    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('featuredProduct')) === true;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return $this->featuredProductRules();
    }

    protected function prepareForValidation(): void
    {
        $this->normalizeFeaturedProductInput();
    }
}
