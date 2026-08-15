<?php

use App\Modules\Orders\Http\Controllers\EnableClosedOrderEditController;
use App\Modules\Orders\Http\Controllers\OrderManagementController;
use App\Modules\Orders\Http\Controllers\OrderPaymentStatusController;
use App\Modules\Orders\Http\Controllers\OrderPricingController;
use App\Modules\Orders\Http\Controllers\OrderStatusController;
use App\Modules\Orders\Http\Controllers\OrderTrashController;
use Illuminate\Support\Facades\Route;

Route::get('/', [OrderManagementController::class, 'home'])->name('home');
Route::get('/orders', [OrderManagementController::class, 'index'])->name('orders.index');
Route::get('/orders/create', [OrderManagementController::class, 'create'])->name('orders.create');
Route::post('/orders', [OrderManagementController::class, 'store'])->name('orders.store');
Route::post('/orders/price-preview', OrderPricingController::class)->name('orders.price-preview');
Route::get('/orders/trash', [OrderTrashController::class, 'index'])->name('orders.trash.index');
Route::patch('/orders/trash/{order}/restore', [OrderTrashController::class, 'restore'])->withTrashed()->name('orders.trash.restore');
Route::get('/orders/{order}/edit', [OrderManagementController::class, 'edit'])->name('orders.edit');
Route::post('/orders/{order}/enable-edit', EnableClosedOrderEditController::class)->name('orders.enable-edit');
Route::put('/orders/{order}', [OrderManagementController::class, 'update'])->name('orders.update');
Route::get('/orders/{order}', [OrderManagementController::class, 'show'])->name('orders.show');
Route::patch('/orders/{order}/status', OrderStatusController::class)->name('orders.status.update');
Route::patch('/orders/{order}/payment-status', OrderPaymentStatusController::class)->name('orders.payment-status.update');
Route::delete('/orders/{order}', [OrderManagementController::class, 'destroy'])->name('orders.destroy');
