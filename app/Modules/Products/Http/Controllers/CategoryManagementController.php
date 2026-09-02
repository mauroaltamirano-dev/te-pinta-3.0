<?php

namespace App\Modules\Products\Http\Controllers;

use App\Modules\Products\Actions\CreateCategory;
use App\Modules\Products\Actions\UpdateCategory;
use App\Modules\Products\Data\CategoryData;
use App\Modules\Products\Http\Requests\StoreCategoryRequest;
use App\Modules\Products\Http\Requests\UpdateCategoryRequest;
use App\Modules\Products\Models\Category;
use Illuminate\Http\RedirectResponse;

final class CategoryManagementController
{
    public function store(StoreCategoryRequest $request, CreateCategory $createCategory): RedirectResponse
    {
        $createCategory->execute(CategoryData::fromRequest($request));

        return to_route('admin.products.index')->with('success', 'Categoría creada.');
    }

    public function update(UpdateCategoryRequest $request, Category $category, UpdateCategory $updateCategory): RedirectResponse
    {
        $updateCategory->execute($category, CategoryData::fromRequest($request));

        return to_route('admin.products.index')->with('success', 'Categoría actualizada.');
    }
}
