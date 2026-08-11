<?php

use App\Modules\Orders\Http\Controllers\OrderPricingController;
use App\Modules\Orders\Http\Controllers\OrderStatusController;
use Illuminate\Support\Facades\Route;

Route::inertia('/orders', 'admin/orders/index')->name('orders.index');
Route::post('/orders/price-preview', OrderPricingController::class)->name('orders.price-preview');
Route::inertia('/orders/{order}', 'admin/orders/show')
    ->middleware('can:view,order')
    ->name('orders.show');
Route::patch('/orders/{order}/status', OrderStatusController::class)
    ->middleware('can:update,order')
    ->name('orders.status.update');
