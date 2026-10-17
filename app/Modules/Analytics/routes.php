<?php

use Illuminate\Support\Facades\Route;

Route::inertia('/analytics', 'admin/analytics/index')->middleware('can:dashboard.view')->name('analytics.index');
