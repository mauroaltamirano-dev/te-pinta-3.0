<?php

namespace App\Modules\Products\Http\Controllers;

use App\Modules\Products\Actions\CreateProductSubcategory;
use App\Modules\Products\Actions\UpdateProductSubcategory;
use App\Modules\Products\Data\CategoryData;
use App\Modules\Products\Http\Requests\StoreProductSubcategoryRequest;
use App\Modules\Products\Http\Requests\UpdateProductSubcategoryRequest;
use App\Modules\Products\Models\Category;
use App\Modules\Products\Models\ProductSubcategory;
use Illuminate\Http\RedirectResponse;

final class ProductSubcategoryManagementController
{
    public function store(StoreProductSubcategoryRequest $request, Category $category, CreateProductSubcategory $createSubcategory): RedirectResponse
    {
        $createSubcategory->execute($category, CategoryData::fromRequest($request));

        return to_route('admin.products.index')->with('success', 'Subcategoría creada.');
    }

    public function update(UpdateProductSubcategoryRequest $request, ProductSubcategory $subcategory, UpdateProductSubcategory $updateSubcategory): RedirectResponse
    {
        $updateSubcategory->execute($subcategory, CategoryData::fromRequest($request));

        return to_route('admin.products.index')->with('success', 'Subcategoría actualizada.');
    }
}
