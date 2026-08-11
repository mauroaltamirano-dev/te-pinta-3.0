<?php

namespace App\Modules\Products\Actions;

use App\Modules\Products\Data\CategoryData;
use App\Modules\Products\Models\Category;
use App\Modules\Products\Support\UniqueSlug;

final class CreateCategory
{
    public function __construct(private readonly UniqueSlug $uniqueSlug) {}

    public function execute(CategoryData $data): Category
    {
        return Category::query()->create([
            'name' => $data->name,
            'slug' => $this->uniqueSlug->for('categories', $data->name),
            'sort_order' => $data->sortOrder,
            'is_active' => $data->isActive,
        ]);
    }
}
