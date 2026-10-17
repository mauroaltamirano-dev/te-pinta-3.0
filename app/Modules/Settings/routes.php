<?php

use App\Modules\Settings\Http\Controllers\BrandingSettingsController;
use App\Modules\Settings\Http\Controllers\OrderingSettingsController;
use Illuminate\Support\Facades\Route;

Route::get('/settings', fn () => to_route('admin.products.index'))->middleware('can:catalog.view')->name('settings.index');
Route::get('/settings/general', [OrderingSettingsController::class, 'index'])->middleware('can:catalog.manage')->name('settings.general.index');
Route::put('/settings/general', [OrderingSettingsController::class, 'update'])->middleware('can:catalog.manage')->name('settings.general.update');
Route::put('/settings/general/branding', [BrandingSettingsController::class, 'update'])->middleware('can:catalog.manage')->name('settings.general.branding.update');
