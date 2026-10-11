<?php

use App\Modules\Inventory\Http\Controllers\InventoryCategoryController;
use App\Modules\Inventory\Http\Controllers\InventoryController;
use App\Modules\Inventory\Http\Controllers\InventoryItemController;
use App\Modules\Inventory\Http\Controllers\ProductionController;
use App\Modules\Inventory\Http\Controllers\PurchasePresentationController;
use App\Modules\Inventory\Http\Controllers\RecipeController;
use Illuminate\Support\Facades\Route;

Route::get('/inventory', [InventoryController::class, 'index'])->name('inventory.index');
Route::post('/inventory/categories', [InventoryCategoryController::class, 'store'])->name('inventory.categories.store');
Route::match(['put', 'patch'], '/inventory/categories/{inventoryCategory}', [InventoryCategoryController::class, 'update'])->name('inventory.categories.update');
Route::post('/inventory/items', [InventoryItemController::class, 'store'])->name('inventory.items.store');
Route::match(['put', 'patch'], '/inventory/items/{inventoryItem}', [InventoryItemController::class, 'update'])->name('inventory.items.update');
Route::post('/inventory/presentations', [PurchasePresentationController::class, 'store'])->name('inventory.presentations.store');
Route::match(['put', 'patch'], '/inventory/presentations/{purchasePresentation}', [PurchasePresentationController::class, 'update'])->name('inventory.presentations.update');

Route::get('/inventory/recipes', [RecipeController::class, 'index'])->name('inventory.recipes.index');
Route::get('/inventory/recipes/create', [RecipeController::class, 'create'])->name('inventory.recipes.create');
Route::post('/inventory/recipes', [RecipeController::class, 'store'])->name('inventory.recipes.store');
Route::get('/inventory/recipes/{recipe}', [RecipeController::class, 'show'])->name('inventory.recipes.show');
Route::post('/inventory/recipes/{recipe}/versions', [RecipeController::class, 'storeVersion'])->name('inventory.recipes.versions.store');
Route::get('/inventory/production', [ProductionController::class, 'index'])->name('inventory.production.index');
Route::get('/inventory/production/create', [ProductionController::class, 'create'])->name('inventory.production.create');
Route::post('/inventory/production', [ProductionController::class, 'store'])->name('inventory.production.store');
Route::get('/inventory/production/{productionBatch}', [ProductionController::class, 'show'])->name('inventory.production.show');
Route::post('/inventory/production/{productionBatch}/reverse', [ProductionController::class, 'reverse'])->name('inventory.production.reverse');
