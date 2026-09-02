<?php

use App\Modules\Instagram\Http\Controllers\InstagramManagementController;
use Illuminate\Support\Facades\Route;

Route::prefix('marketing/instagram')->name('marketing.instagram.')->group(function (): void {
    Route::get('/', [InstagramManagementController::class, 'index'])->name('index');
    Route::patch('/automation', [InstagramManagementController::class, 'updateAutomation'])->name('automation.update');
    Route::post('/assets', [InstagramManagementController::class, 'storeAsset'])->name('assets.store');
    Route::patch('/assets/{asset}', [InstagramManagementController::class, 'updateAsset'])->name('assets.update');
    Route::delete('/assets/{asset}', [InstagramManagementController::class, 'destroyAsset'])->name('assets.destroy');
    Route::post('/schedules', [InstagramManagementController::class, 'storeSchedule'])->name('schedules.store');
    Route::patch('/schedules/{schedule}', [InstagramManagementController::class, 'updateSchedule'])->name('schedules.update');
    Route::delete('/schedules/{schedule}', [InstagramManagementController::class, 'destroySchedule'])->name('schedules.destroy');
    Route::post('/publications', [InstagramManagementController::class, 'storePublication'])->name('publications.store');
    Route::post('/publications/archive-old', [InstagramManagementController::class, 'archiveOldPublications'])->name('publications.archive-old');
    Route::patch('/publications/{publication}/archive', [InstagramManagementController::class, 'archivePublication'])->name('publications.archive');
    Route::post('/publications/{publication}/retry', [InstagramManagementController::class, 'retryPublication'])->name('publications.retry');
    Route::post('/publications/{publication}/cancel', [InstagramManagementController::class, 'cancelPublication'])->name('publications.cancel');
});
