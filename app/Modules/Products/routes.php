<?php

use App\Modules\Products\Http\Controllers\CategoryManagementController;
use App\Modules\Products\Http\Controllers\ProductManagementController;
use App\Modules\Products\Http\Controllers\TagManagementController;
use Illuminate\Support\Facades\Route;

Route::get('/settings/products', [ProductManagementController::class, 'index'])->name('products.index');
Route::post('/settings/products', [ProductManagementController::class, 'store'])->name('products.store');
Route::match(['put', 'patch'], '/settings/products/{product}', [ProductManagementController::class, 'update'])->name('products.update');
Route::delete('/settings/products/{product}', [ProductManagementController::class, 'destroy'])->name('products.destroy');

Route::post('/settings/categories', [CategoryManagementController::class, 'store'])->name('product-categories.store');
Route::match(['put', 'patch'], '/settings/categories/{category}', [CategoryManagementController::class, 'update'])->name('product-categories.update');

Route::post('/settings/tags', [TagManagementController::class, 'store'])->name('product-tags.store');
Route::match(['put', 'patch'], '/settings/tags/{tag}', [TagManagementController::class, 'update'])->name('product-tags.update');
Route::delete('/settings/tags/{tag}', [TagManagementController::class, 'destroy'])->name('product-tags.destroy');
