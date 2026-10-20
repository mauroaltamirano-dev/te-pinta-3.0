<?php

use App\Modules\Printing\Http\Controllers\PrintJobController;
use Illuminate\Support\Facades\Route;

Route::get('/print-jobs/status', [PrintJobController::class, 'status'])->middleware('can:orders.manage')->name('print-jobs.status');
Route::get('/print-jobs', [PrintJobController::class, 'recent'])->middleware('can:orders.manage')->name('print-jobs.index');
Route::post('/print-jobs/claim', [PrintJobController::class, 'claim'])->middleware('can:orders.manage')->name('print-jobs.claim');
Route::post('/print-jobs/{job}/printed', [PrintJobController::class, 'printed'])->whereNumber('job')->middleware('can:orders.manage')->name('print-jobs.printed');
Route::post('/print-jobs/{job}/release', [PrintJobController::class, 'release'])->whereNumber('job')->middleware('can:orders.manage')->name('print-jobs.release');
Route::post('/print-jobs/{job}/reprint', [PrintJobController::class, 'reprint'])->whereNumber('job')->middleware('can:orders.manage')->name('print-jobs.reprint');
