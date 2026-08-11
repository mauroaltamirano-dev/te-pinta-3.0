<?php

use Illuminate\Support\Facades\Route;

Route::inertia('/customers', 'admin/customers/index')->name('customers.index');
Route::inertia('/customers/{customer}', 'admin/customers/show')
    ->middleware('can:view,customer')
    ->name('customers.show');
