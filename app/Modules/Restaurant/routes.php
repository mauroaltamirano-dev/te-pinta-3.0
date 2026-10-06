<?php

use App\Modules\Restaurant\Http\Controllers\FloorLayoutController;
use Illuminate\Support\Facades\Route;

Route::get('/restaurant/floor', [FloorLayoutController::class, 'index'])->name('restaurant.floor');
Route::post('/restaurant/areas', [FloorLayoutController::class, 'store'])->name('restaurant.areas.store');
Route::put('/restaurant/areas/{area}/layout', [FloorLayoutController::class, 'update'])->name('restaurant.layout.update');
