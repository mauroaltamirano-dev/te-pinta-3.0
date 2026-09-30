<?php

use App\Modules\Marketing\Http\Controllers\MarketingManagementController;
use Illuminate\Support\Facades\Route;

Route::get('/marketing', [MarketingManagementController::class, 'index'])->name('marketing.index');
Route::get('/marketing/products/{product}/featured', [MarketingManagementController::class, 'forProduct'])->name('marketing.products.featured');
Route::post('/marketing/featured-products', [MarketingManagementController::class, 'store'])->name('marketing.featured-products.store');
Route::patch('/marketing/featured-products/{featuredProduct}', [MarketingManagementController::class, 'update'])->name('marketing.featured-products.update');
Route::patch('/marketing/featured-products/{featuredProduct}/disable', [MarketingManagementController::class, 'disable'])->name('marketing.featured-products.disable');
