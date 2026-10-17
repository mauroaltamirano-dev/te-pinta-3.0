<?php

use App\Modules\Orders\Http\Controllers\EnableClosedOrderEditController;
use App\Modules\Orders\Http\Controllers\OrderManagementController;
use App\Modules\Orders\Http\Controllers\OrderPaymentStatusController;
use App\Modules\Orders\Http\Controllers\OrderPricingController;
use App\Modules\Orders\Http\Controllers\OrderStatusController;
use App\Modules\Orders\Http\Controllers\OrderTrashController;
use Illuminate\Support\Facades\Route;

Route::get('/', [OrderManagementController::class, 'home'])->middleware('can:orders.view')->name('home');
Route::get('/orders', [OrderManagementController::class, 'index'])->middleware('can:orders.view')->name('orders.index');
Route::get('/orders/create', [OrderManagementController::class, 'create'])->middleware('can:orders.manage')->name('orders.create');
Route::post('/orders', [OrderManagementController::class, 'store'])->middleware('can:orders.manage')->name('orders.store');
Route::post('/orders/price-preview', OrderPricingController::class)->middleware('can:orders.manage')->name('orders.price-preview');
Route::get('/orders/trash', [OrderTrashController::class, 'index'])->middleware('can:orders.view')->name('orders.trash.index');
Route::patch('/orders/trash/{order}/restore', [OrderTrashController::class, 'restore'])->withTrashed()->middleware('can:orders.manage')->name('orders.trash.restore');
Route::get('/orders/{order}/edit', [OrderManagementController::class, 'edit'])->middleware('can:orders.manage')->name('orders.edit');
Route::post('/orders/{order}/enable-edit', EnableClosedOrderEditController::class)->middleware('can:orders.manage')->name('orders.enable-edit');
Route::put('/orders/{order}', [OrderManagementController::class, 'update'])->middleware('can:orders.manage')->name('orders.update');
Route::get('/orders/{order}', [OrderManagementController::class, 'show'])->middleware('can:orders.view')->name('orders.show');
Route::patch('/orders/{order}/status', OrderStatusController::class)->middleware('can:orders.manage')->name('orders.status.update');
Route::patch('/orders/{order}/payment-status', OrderPaymentStatusController::class)->middleware('can:orders.manage')->name('orders.payment-status.update');
Route::delete('/orders/{order}', [OrderManagementController::class, 'destroy'])->middleware('can:orders.manage')->name('orders.destroy');
