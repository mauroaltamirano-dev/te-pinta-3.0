<?php

use App\Modules\Identity\Http\Controllers\UserManagementController;
use Illuminate\Support\Facades\Route;

Route::controller(UserManagementController::class)
    ->prefix('users')
    ->name('users.')
    ->middleware('can:users.manage')
    ->group(function (): void {
        Route::get('/', 'index')->name('index');
        Route::post('/', 'store')->name('store');
        Route::patch('/{user}/role', 'updateRole')->whereNumber('user')->name('role.update');
        Route::post('/{user}/deactivate', 'deactivate')->whereNumber('user')->name('deactivate');
        Route::post('/{user}/activate', 'activate')->whereNumber('user')->name('activate');
    });
