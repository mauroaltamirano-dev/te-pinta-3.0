<?php

use App\Modules\Management\Http\Controllers\ManagementAttachmentController;
use App\Modules\Management\Http\Controllers\ManagementAuditController;
use App\Modules\Management\Http\Controllers\ManagementController;
use App\Modules\Management\Http\Controllers\PurchaseController;
use Illuminate\Support\Facades\Route;

Route::get('/management', [ManagementController::class, 'index'])->name('management.index');
Route::get('/management/audit', ManagementAuditController::class)->name('management.audit.index');
Route::post('/management/wallets', [ManagementController::class, 'storeWallet'])->name('management.wallets.store');
Route::patch('/management/wallets/{wallet}', [ManagementController::class, 'updateWallet'])->name('management.wallets.update');
Route::post('/management/wallets/{wallet}/archive', [ManagementController::class, 'archiveWallet'])->name('management.wallets.archive');
Route::post('/management/wallets/{wallet}/restore', [ManagementController::class, 'restoreWallet'])->name('management.wallets.restore');
Route::post('/management/transactions', [ManagementController::class, 'storeTransaction'])->name('management.transactions.store');
Route::post('/management/outflows', [ManagementController::class, 'storeOutflow'])->name('management.outflows.store');
Route::post('/management/transfers', [ManagementController::class, 'storeTransfer'])->name('management.transfers.store');
Route::post('/management/distribution-rules', [ManagementController::class, 'storeDistributionRule'])->name('management.distribution-rules.store');
Route::post('/management/distribution-rules/{distributionRule}/versions', [ManagementController::class, 'storeDistributionRuleVersion'])->name('management.distribution-rules.versions.store');
Route::patch('/management/distribution-rules/{distributionRule}/status', [ManagementController::class, 'updateDistributionRuleStatus'])->name('management.distribution-rules.status.update');
Route::post('/management/distributions', [ManagementController::class, 'storeDistribution'])->name('management.distributions.store');
Route::post('/management/order-payments/{managementOrderPayment}/distribution', [ManagementController::class, 'distributeOrderPayment'])->name('management.order-payments.distribution.store');
Route::post('/management/transactions/{financialTransaction}/reverse', [ManagementController::class, 'reverseTransaction'])->name('management.transactions.reverse');
Route::get('/management/transactions/{financialTransaction}/attachments', [ManagementAttachmentController::class, 'index'])->name('management.transactions.attachments.index');
Route::post('/management/transactions/{financialTransaction}/attachments', [ManagementAttachmentController::class, 'store'])->name('management.transactions.attachments.store');
Route::get('/management/transactions/{financialTransaction}/attachments/{managementAttachment}', [ManagementAttachmentController::class, 'show'])->name('management.transactions.attachments.show');
Route::delete('/management/transactions/{financialTransaction}/attachments/{managementAttachment}', [ManagementAttachmentController::class, 'destroy'])->name('management.transactions.attachments.destroy');

Route::controller(PurchaseController::class)->prefix('management/purchases')->name('management.purchases.')->group(function (): void {
    Route::get('/', 'index')->name('index');
    Route::get('/create', 'create')->name('create');
    Route::post('/', 'store')->name('store');
    Route::get('/{purchase}', 'show')->name('show');
    Route::post('/{purchase}/reverse', 'reverse')->name('reverse');
});
