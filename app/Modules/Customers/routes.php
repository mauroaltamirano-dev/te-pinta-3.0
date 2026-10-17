<?php

use App\Modules\Customers\Http\Controllers\CustomerManagementController;
use App\Modules\Customers\Http\Controllers\CustomerTrashController;
use Illuminate\Support\Facades\Route;

Route::get('/customers', [CustomerManagementController::class, 'index'])->middleware('can:customers.view')->name('customers.index');
Route::post('/customers', [CustomerManagementController::class, 'store'])->middleware('can:customers.manage')->name('customers.store');
Route::get('/customers/trash', [CustomerTrashController::class, 'index'])->middleware('can:customers.view')->name('customers.trash.index');
Route::patch('/customers/trash/{customer}/restore', [CustomerTrashController::class, 'restore'])->withTrashed()->middleware('can:customers.manage')->name('customers.trash.restore');
Route::get('/customers/{customer}', [CustomerManagementController::class, 'show'])->middleware('can:customers.view')->name('customers.show');
Route::match(['put', 'patch'], '/customers/{customer}', [CustomerManagementController::class, 'update'])->middleware('can:customers.manage')->name('customers.update');
Route::delete('/customers/{customer}', [CustomerManagementController::class, 'destroy'])->middleware('can:customers.manage')->name('customers.destroy');
