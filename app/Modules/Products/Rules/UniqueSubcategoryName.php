<?php

namespace App\Modules\Products\Rules;

use App\Modules\Products\Models\ProductSubcategory;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/** Case-insensitive, like the `product_subcategories_name_unique` index. */
final readonly class UniqueSubcategoryName implements ValidationRule
{
    public function __construct(private int $categoryId, private ?int $exceptId = null) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $taken = ProductSubcategory::query()
            ->where('category_id', $this->categoryId)
            ->whereRaw('lower(name) = lower(?)', [(string) $value])
            ->when($this->exceptId !== null, fn ($query) => $query->whereKeyNot($this->exceptId))
            ->exists();

        if ($taken) {
            $fail('Ya existe una subcategoría con ese nombre en esta categoría.');
        }
    }
}
