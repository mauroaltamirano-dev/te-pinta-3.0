<?php

namespace App\Modules\Products\Actions;

use App\Modules\Products\Data\CategoryData;
use App\Modules\Products\Models\Category;
use App\Modules\Products\Support\UniqueSlug;

final class UpdateCategory
{
    public function __construct(private readonly UniqueSlug $uniqueSlug) {}

    public function execute(Category $category, CategoryData $data): Category
    {
        $category->update([
            'name' => $data->name,
            'slug' => $this->uniqueSlug->for('categories', $data->name, $category->id),
            'sort_order' => $data->sortOrder,
            'is_active' => $data->isActive,
        ]);

        return $category->refresh();
    }
}
