<?php

use App\Modules\Orders\Http\Controllers\OrderManagementController;
use App\Modules\Orders\Http\Controllers\OrderPaymentStatusController;
use App\Modules\Orders\Http\Controllers\OrderPricingController;
use App\Modules\Orders\Http\Controllers\OrderStatusController;
use Illuminate\Support\Facades\Route;

Route::get('/orders', [OrderManagementController::class, 'index'])->name('orders.index');
Route::get('/orders/create', [OrderManagementController::class, 'create'])->name('orders.create');
Route::post('/orders', [OrderManagementController::class, 'store'])->name('orders.store');
Route::post('/orders/price-preview', OrderPricingController::class)->name('orders.price-preview');
Route::get('/orders/{order}', [OrderManagementController::class, 'show'])->name('orders.show');
Route::patch('/orders/{order}/status', OrderStatusController::class)->name('orders.status.update');
Route::patch('/orders/{order}/payment-status', OrderPaymentStatusController::class)->name('orders.payment-status.update');
Route::delete('/orders/{order}', [OrderManagementController::class, 'destroy'])->name('orders.destroy');
