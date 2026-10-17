<?php

use App\Modules\Marketing\Http\Controllers\MarketingManagementController;
use Illuminate\Support\Facades\Route;

Route::get('/marketing', [MarketingManagementController::class, 'index'])->middleware('can:marketing.manage')->name('marketing.index');
Route::get('/marketing/products/{product}/featured', [MarketingManagementController::class, 'forProduct'])->middleware('can:marketing.manage')->name('marketing.products.featured');
Route::post('/marketing/featured-products', [MarketingManagementController::class, 'store'])->middleware('can:marketing.manage')->name('marketing.featured-products.store');
Route::patch('/marketing/featured-products/{featuredProduct}', [MarketingManagementController::class, 'update'])->middleware('can:marketing.manage')->name('marketing.featured-products.update');
Route::patch('/marketing/featured-products/{featuredProduct}/disable', [MarketingManagementController::class, 'disable'])->middleware('can:marketing.manage')->name('marketing.featured-products.disable');
