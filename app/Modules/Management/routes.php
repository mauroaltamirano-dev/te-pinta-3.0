<?php

use App\Modules\Management\Http\Controllers\ManagementAttachmentController;
use App\Modules\Management\Http\Controllers\ManagementAuditController;
use App\Modules\Management\Http\Controllers\ManagementController;
use App\Modules\Management\Http\Controllers\PurchaseController;
use Illuminate\Support\Facades\Route;

Route::get('/management', [ManagementController::class, 'index'])->middleware('can:finance.view')->name('management.index');
Route::get('/management/audit', ManagementAuditController::class)->middleware('can:finance.view')->name('management.audit.index');
Route::post('/management/wallets', [ManagementController::class, 'storeWallet'])->middleware('can:finance.manage')->name('management.wallets.store');
Route::patch('/management/wallets/{wallet}', [ManagementController::class, 'updateWallet'])->middleware('can:finance.manage')->name('management.wallets.update');
Route::post('/management/wallets/{wallet}/archive', [ManagementController::class, 'archiveWallet'])->middleware('can:finance.manage')->name('management.wallets.archive');
Route::post('/management/wallets/{wallet}/restore', [ManagementController::class, 'restoreWallet'])->middleware('can:finance.manage')->name('management.wallets.restore');
Route::post('/management/transactions', [ManagementController::class, 'storeTransaction'])->middleware('can:finance.manage')->name('management.transactions.store');
Route::post('/management/outflows', [ManagementController::class, 'storeOutflow'])->middleware('can:finance.manage')->name('management.outflows.store');
Route::post('/management/transfers', [ManagementController::class, 'storeTransfer'])->middleware('can:finance.manage')->name('management.transfers.store');
Route::post('/management/distribution-rules', [ManagementController::class, 'storeDistributionRule'])->middleware('can:finance.manage')->name('management.distribution-rules.store');
Route::post('/management/distribution-rules/{distributionRule}/versions', [ManagementController::class, 'storeDistributionRuleVersion'])->middleware('can:finance.manage')->name('management.distribution-rules.versions.store');
Route::patch('/management/distribution-rules/{distributionRule}/status', [ManagementController::class, 'updateDistributionRuleStatus'])->middleware('can:finance.manage')->name('management.distribution-rules.status.update');
Route::post('/management/distributions', [ManagementController::class, 'storeDistribution'])->middleware('can:finance.manage')->name('management.distributions.store');
Route::post('/management/order-payments/{managementOrderPayment}/distribution', [ManagementController::class, 'distributeOrderPayment'])->middleware('can:finance.manage')->name('management.order-payments.distribution.store');
Route::post('/management/transactions/{financialTransaction}/reverse', [ManagementController::class, 'reverseTransaction'])->middleware('can:finance.manage')->name('management.transactions.reverse');
Route::get('/management/transactions/{financialTransaction}/attachments', [ManagementAttachmentController::class, 'index'])->middleware('can:finance.view')->name('management.transactions.attachments.index');
Route::post('/management/transactions/{financialTransaction}/attachments', [ManagementAttachmentController::class, 'store'])->middleware('can:finance.manage')->name('management.transactions.attachments.store');
Route::get('/management/transactions/{financialTransaction}/attachments/{managementAttachment}', [ManagementAttachmentController::class, 'show'])->middleware('can:finance.view')->name('management.transactions.attachments.show');
Route::delete('/management/transactions/{financialTransaction}/attachments/{managementAttachment}', [ManagementAttachmentController::class, 'destroy'])->middleware('can:finance.manage')->name('management.transactions.attachments.destroy');

Route::controller(PurchaseController::class)->prefix('management/purchases')->name('management.purchases.')->group(function (): void {
    Route::get('/', 'index')->middleware('can:finance.view')->name('index');
    Route::get('/create', 'create')->middleware('can:finance.manage')->name('create');
    Route::post('/', 'store')->middleware('can:finance.manage')->name('store');
    Route::get('/{purchase}', 'show')->middleware('can:finance.view')->name('show');
    Route::post('/{purchase}/reverse', 'reverse')->middleware('can:finance.manage')->name('reverse');
});
