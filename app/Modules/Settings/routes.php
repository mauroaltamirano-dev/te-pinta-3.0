<?php

use Illuminate\Support\Facades\Route;

Route::get('/settings', fn () => to_route('admin.products.index'))->name('settings.index');
