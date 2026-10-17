<?php

use App\Modules\Products\Http\Controllers\CategoryManagementController;
use App\Modules\Products\Http\Controllers\HalfDozenPricePreviewController;
use App\Modules\Products\Http\Controllers\ProductManagementController;
use App\Modules\Products\Http\Controllers\TagManagementController;
use Illuminate\Support\Facades\Route;

Route::get('/settings/products', [ProductManagementController::class, 'index'])->middleware('can:catalog.view')->name('products.index');
Route::post('/settings/products/half-dozen-preview', HalfDozenPricePreviewController::class)->middleware('can:catalog.manage')->name('products.half-dozen-preview');
Route::post('/settings/products', [ProductManagementController::class, 'store'])->middleware('can:catalog.manage')->name('products.store');
Route::match(['put', 'patch'], '/settings/products/{product}', [ProductManagementController::class, 'update'])->middleware('can:catalog.manage')->name('products.update');
Route::delete('/settings/products/{product}', [ProductManagementController::class, 'destroy'])->middleware('can:catalog.manage')->name('products.destroy');

Route::post('/settings/categories', [CategoryManagementController::class, 'store'])->middleware('can:catalog.manage')->name('product-categories.store');
Route::match(['put', 'patch'], '/settings/categories/{category}', [CategoryManagementController::class, 'update'])->middleware('can:catalog.manage')->name('product-categories.update');

Route::post('/settings/tags', [TagManagementController::class, 'store'])->middleware('can:catalog.manage')->name('product-tags.store');
Route::match(['put', 'patch'], '/settings/tags/{tag}', [TagManagementController::class, 'update'])->middleware('can:catalog.manage')->name('product-tags.update');
Route::delete('/settings/tags/{tag}', [TagManagementController::class, 'destroy'])->middleware('can:catalog.manage')->name('product-tags.destroy');
