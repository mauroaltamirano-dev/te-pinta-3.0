<?php

use App\Modules\Products\Http\Controllers\PublicProductCatalogController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'public/home')->name('home');
Route::inertia('/menu', 'public/menu')->name('menu');
Route::inertia('/cart', 'public/cart')->name('cart');

Route::get('/menu/catalog', PublicProductCatalogController::class)->name('public.products.index');
