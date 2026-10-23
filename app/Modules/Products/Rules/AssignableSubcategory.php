<?php

namespace App\Modules\Products\Rules;

use App\Modules\Products\Models\ProductSubcategory;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/** A product's subcategory must belong to its category; an inactive one is kept but never newly assigned. */
final readonly class AssignableSubcategory implements ValidationRule
{
    public function __construct(private mixed $categoryId, private ?int $currentSubcategoryId = null) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $subcategory = is_numeric($value) ? ProductSubcategory::query()->find((int) $value) : null;

        if ($subcategory === null || ! is_numeric($this->categoryId) || $subcategory->category_id !== (int) $this->categoryId) {
            $fail('La subcategoría elegida no pertenece a la categoría del producto.');

            return;
        }

        if (! $subcategory->is_active && $subcategory->id !== $this->currentSubcategoryId) {
            $fail('La subcategoría elegida está inactiva.');
        }
    }
}
