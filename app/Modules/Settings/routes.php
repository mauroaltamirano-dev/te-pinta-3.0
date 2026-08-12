<?php

use App\Modules\Settings\Http\Controllers\OrderingSettingsController;
use Illuminate\Support\Facades\Route;

Route::get('/settings', fn () => to_route('admin.products.index'))->name('settings.index');
Route::get('/settings/general', [OrderingSettingsController::class, 'index'])->name('settings.general.index');
Route::put('/settings/general', [OrderingSettingsController::class, 'update'])->name('settings.general.update');
