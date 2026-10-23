<?php

namespace App\Modules\Products\Actions;

use App\Modules\Products\Data\CategoryData;
use App\Modules\Products\Models\ProductSubcategory;
use App\Modules\Products\Support\UniqueSlug;

final class UpdateProductSubcategory
{
    public function __construct(private readonly UniqueSlug $uniqueSlug) {}

    /** The category never changes: its products' composite foreign key binds the subcategory to it. */
    public function execute(ProductSubcategory $subcategory, CategoryData $data): ProductSubcategory
    {
        $subcategory->update([
            'name' => $data->name,
            'slug' => $this->uniqueSlug->for('product_subcategories', $data->name, $subcategory->id, ['category_id' => $subcategory->category_id]),
            'sort_order' => $data->sortOrder,
            'is_active' => $data->isActive,
        ]);

        return $subcategory->refresh();
    }
}
