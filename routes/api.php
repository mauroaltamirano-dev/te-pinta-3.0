<?php

use App\Modules\Instagram\Http\Controllers\InstagramAutomationApiController;
use Illuminate\Support\Facades\Route;

Route::middleware(['throttle:instagram-automation', 'n8n.automation'])
    ->prefix('automation/instagram/stories')
    ->name('automation.instagram.stories.')
    ->group(function (): void {
        Route::get('/pending', [InstagramAutomationApiController::class, 'pending'])->name('pending');
        Route::post('/{publication}/published', [InstagramAutomationApiController::class, 'published'])->whereNumber('publication')->name('published');
        Route::post('/{publication}/failed', [InstagramAutomationApiController::class, 'failed'])->whereNumber('publication')->name('failed');
    });
