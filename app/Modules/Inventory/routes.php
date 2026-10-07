<?php

use App\Modules\Inventory\Http\Controllers\InventoryCategoryController;
use App\Modules\Inventory\Http\Controllers\InventoryController;
use App\Modules\Inventory\Http\Controllers\InventoryItemController;
use App\Modules\Inventory\Http\Controllers\PurchasePresentationController;
use Illuminate\Support\Facades\Route;

Route::get('/inventory', [InventoryController::class, 'index'])->name('inventory.index');
Route::post('/inventory/categories', [InventoryCategoryController::class, 'store'])->name('inventory.categories.store');
Route::match(['put', 'patch'], '/inventory/categories/{inventoryCategory}', [InventoryCategoryController::class, 'update'])->name('inventory.categories.update');
Route::post('/inventory/items', [InventoryItemController::class, 'store'])->name('inventory.items.store');
Route::match(['put', 'patch'], '/inventory/items/{inventoryItem}', [InventoryItemController::class, 'update'])->name('inventory.items.update');
Route::post('/inventory/presentations', [PurchasePresentationController::class, 'store'])->name('inventory.presentations.store');
Route::match(['put', 'patch'], '/inventory/presentations/{purchasePresentation}', [PurchasePresentationController::class, 'update'])->name('inventory.presentations.update');
