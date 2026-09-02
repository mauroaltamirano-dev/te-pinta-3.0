<?php

namespace App\Modules\Marketing\Http\Requests;

use App\Modules\Marketing\Models\FeaturedProduct;
use Illuminate\Foundation\Http\FormRequest;

final class StoreFeaturedProductRequest extends FormRequest
{
    use ValidatesFeaturedProduct;

    public function authorize(): bool
    {
        return $this->user()?->can('create', FeaturedProduct::class) === true;
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
