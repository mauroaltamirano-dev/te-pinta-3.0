<?php

use App\Modules\Customers\Http\Controllers\CustomerManagementController;
use App\Modules\Customers\Http\Controllers\CustomerTrashController;
use Illuminate\Support\Facades\Route;

Route::get('/customers', [CustomerManagementController::class, 'index'])->name('customers.index');
Route::post('/customers', [CustomerManagementController::class, 'store'])->name('customers.store');
Route::get('/customers/trash', [CustomerTrashController::class, 'index'])->name('customers.trash.index');
Route::patch('/customers/trash/{customer}/restore', [CustomerTrashController::class, 'restore'])->withTrashed()->name('customers.trash.restore');
Route::get('/customers/{customer}', [CustomerManagementController::class, 'show'])->name('customers.show');
Route::match(['put', 'patch'], '/customers/{customer}', [CustomerManagementController::class, 'update'])->name('customers.update');
Route::delete('/customers/{customer}', [CustomerManagementController::class, 'destroy'])->name('customers.destroy');
