<?php

use App\Modules\Instagram\Http\Controllers\InstagramManagementController;
use Illuminate\Support\Facades\Route;

Route::prefix('marketing/instagram')->name('marketing.instagram.')->group(function (): void {
    Route::get('/', [InstagramManagementController::class, 'index'])->middleware('can:marketing.manage')->name('index');
    Route::patch('/automation', [InstagramManagementController::class, 'updateAutomation'])->middleware('can:marketing.manage')->name('automation.update');
    Route::post('/assets', [InstagramManagementController::class, 'storeAsset'])->middleware('can:marketing.manage')->name('assets.store');
    Route::patch('/assets/{asset}', [InstagramManagementController::class, 'updateAsset'])->middleware('can:marketing.manage')->name('assets.update');
    Route::delete('/assets/{asset}', [InstagramManagementController::class, 'destroyAsset'])->middleware('can:marketing.manage')->name('assets.destroy');
    Route::post('/schedules', [InstagramManagementController::class, 'storeSchedule'])->middleware('can:marketing.manage')->name('schedules.store');
    Route::patch('/schedules/{schedule}', [InstagramManagementController::class, 'updateSchedule'])->middleware('can:marketing.manage')->name('schedules.update');
    Route::delete('/schedules/{schedule}', [InstagramManagementController::class, 'destroySchedule'])->middleware('can:marketing.manage')->name('schedules.destroy');
    Route::post('/publications', [InstagramManagementController::class, 'storePublication'])->middleware('can:marketing.manage')->name('publications.store');
    Route::post('/publications/archive-old', [InstagramManagementController::class, 'archiveOldPublications'])->middleware('can:marketing.manage')->name('publications.archive-old');
    Route::patch('/publications/{publication}/archive', [InstagramManagementController::class, 'archivePublication'])->middleware('can:marketing.manage')->name('publications.archive');
    Route::post('/publications/{publication}/retry', [InstagramManagementController::class, 'retryPublication'])->middleware('can:marketing.manage')->name('publications.retry');
    Route::post('/publications/{publication}/cancel', [InstagramManagementController::class, 'cancelPublication'])->middleware('can:marketing.manage')->name('publications.cancel');
});
