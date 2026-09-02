<?php

use App\Modules\Instagram\Http\Controllers\InstagramStoryMediaController;
use Illuminate\Support\Facades\Route;

Route::get('/media/instagram/{asset}/{filename}', InstagramStoryMediaController::class)
    ->middleware(['signed:relative', 'throttle:120,1'])
    ->whereNumber('asset')
    ->where('filename', 'story\.jpg')
    ->name('instagram.media.show');
