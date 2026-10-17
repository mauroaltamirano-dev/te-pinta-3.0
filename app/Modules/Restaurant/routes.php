<?php

use App\Modules\Restaurant\Http\Controllers\FloorLayoutController;
use Illuminate\Support\Facades\Route;

Route::get('/restaurant/floor', [FloorLayoutController::class, 'index'])->middleware('can:orders.manage')->name('restaurant.floor');
Route::post('/restaurant/areas', [FloorLayoutController::class, 'store'])->middleware('can:floor.manage')->name('restaurant.areas.store');
Route::put('/restaurant/areas/{area}/layout', [FloorLayoutController::class, 'update'])->middleware('can:floor.manage')->name('restaurant.layout.update');
