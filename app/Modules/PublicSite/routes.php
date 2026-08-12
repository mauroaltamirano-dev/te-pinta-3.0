<?php

use App\Modules\Orders\Http\Controllers\PublicCheckoutOptionsController;
use App\Modules\Orders\Http\Controllers\PublicOrderController;
use App\Modules\Orders\Http\Controllers\PublicOrderPricingController;
use App\Modules\Products\Http\Controllers\PublicProductCatalogController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'public/home')->name('home');
Route::inertia('/menu', 'public/menu')->name('menu');
Route::inertia('/cart', 'public/cart')->name('cart');

Route::get('/menu/catalog', PublicProductCatalogController::class)->name('public.products.index');
Route::get('/checkout/options', PublicCheckoutOptionsController::class)->name('public.orders.options');
Route::post('/checkout/price-preview', PublicOrderPricingController::class)->middleware('throttle:20,1')->name('public.orders.price-preview');
Route::post('/checkout/orders', PublicOrderController::class)->middleware('throttle:5,1')->name('public.orders.store');
