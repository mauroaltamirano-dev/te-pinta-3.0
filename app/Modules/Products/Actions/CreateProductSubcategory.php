<?php

namespace App\Modules\Products\Actions;

use App\Modules\Products\Data\CategoryData;
use App\Modules\Products\Models\Category;
use App\Modules\Products\Models\ProductSubcategory;
use App\Modules\Products\Support\UniqueSlug;

final class CreateProductSubcategory
{
    public function __construct(private readonly UniqueSlug $uniqueSlug) {}

    public function execute(Category $category, CategoryData $data): ProductSubcategory
    {
        return ProductSubcategory::query()->create([
            'category_id' => $category->id,
            'name' => $data->name,
            'slug' => $this->uniqueSlug->for('product_subcategories', $data->name, scope: ['category_id' => $category->id]),
            'sort_order' => $data->sortOrder,
            'is_active' => $data->isActive,
        ]);
    }
}
