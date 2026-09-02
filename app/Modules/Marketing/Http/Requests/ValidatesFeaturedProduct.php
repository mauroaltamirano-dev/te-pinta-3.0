<?php

namespace App\Modules\Marketing\Http\Requests;

use App\Modules\Marketing\Rules\PlainText;
use Illuminate\Validation\Rule;

trait ValidatesFeaturedProduct
{
    /** @return array<string, array<int, mixed>> */
    protected function featuredProductRules(): array
    {
        return [
            'product_id' => ['required', 'integer', Rule::exists('products', 'id')],
            'headline' => ['nullable', 'string', 'max:80', new PlainText],
            'short_copy' => ['nullable', 'string', 'max:240', new PlainText],
            'starts_at' => ['required', 'date_format:Y-m-d\TH:i'],
            'ends_at' => ['nullable', 'date_format:Y-m-d\TH:i', 'after:starts_at'],
            'is_enabled' => ['required', 'boolean'],
        ];
    }

    protected function normalizeFeaturedProductInput(): void
    {
        $this->merge([
            'headline' => $this->filled('headline') ? trim((string) $this->input('headline')) : null,
            'short_copy' => $this->filled('short_copy') ? trim((string) $this->input('short_copy')) : null,
            'ends_at' => $this->filled('ends_at') ? $this->input('ends_at') : null,
        ]);
    }
}
