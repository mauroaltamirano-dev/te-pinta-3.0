<?php

use App\Modules\Inventory\Http\Controllers\CostingPolicyController;
use App\Modules\Inventory\Http\Controllers\InventoryAdjustmentController;
use App\Modules\Inventory\Http\Controllers\InventoryCategoryController;
use App\Modules\Inventory\Http\Controllers\InventoryController;
use App\Modules\Inventory\Http\Controllers\InventoryItemController;
use App\Modules\Inventory\Http\Controllers\ProductCostingController;
use App\Modules\Inventory\Http\Controllers\ProductInventoryConsumptionController;
use App\Modules\Inventory\Http\Controllers\ProductionController;
use App\Modules\Inventory\Http\Controllers\PurchasePresentationController;
use App\Modules\Inventory\Http\Controllers\RecipeController;
use Illuminate\Support\Facades\Route;

Route::get('/inventory', [InventoryController::class, 'index'])->middleware('can:inventory.view')->name('inventory.index');
Route::post('/inventory/categories', [InventoryCategoryController::class, 'store'])->middleware('can:inventory.manage')->name('inventory.categories.store');
Route::match(['put', 'patch'], '/inventory/categories/{inventoryCategory}', [InventoryCategoryController::class, 'update'])->middleware('can:inventory.manage')->name('inventory.categories.update');
Route::post('/inventory/items', [InventoryItemController::class, 'store'])->middleware('can:inventory.manage')->name('inventory.items.store');
Route::match(['put', 'patch'], '/inventory/items/{inventoryItem}', [InventoryItemController::class, 'update'])->middleware('can:inventory.manage')->name('inventory.items.update');
Route::post('/inventory/presentations', [PurchasePresentationController::class, 'store'])->middleware('can:inventory.manage')->name('inventory.presentations.store');
Route::match(['put', 'patch'], '/inventory/presentations/{purchasePresentation}', [PurchasePresentationController::class, 'update'])->middleware('can:inventory.manage')->name('inventory.presentations.update');

Route::get('/inventory/recipes', [RecipeController::class, 'index'])->middleware('can:inventory.view')->name('inventory.recipes.index');
Route::get('/inventory/recipes/create', [RecipeController::class, 'create'])->middleware('can:inventory.manage')->name('inventory.recipes.create');
Route::post('/inventory/recipes', [RecipeController::class, 'store'])->middleware('can:inventory.manage')->name('inventory.recipes.store');
Route::get('/inventory/recipes/{recipe}', [RecipeController::class, 'show'])->middleware('can:inventory.view')->name('inventory.recipes.show');
Route::post('/inventory/recipes/{recipe}/versions', [RecipeController::class, 'storeVersion'])->middleware('can:inventory.manage')->name('inventory.recipes.versions.store');
Route::get('/inventory/production', [ProductionController::class, 'index'])->middleware('can:inventory.view')->name('inventory.production.index');
Route::get('/inventory/production/create', [ProductionController::class, 'create'])->middleware('can:inventory.manage')->name('inventory.production.create');
Route::post('/inventory/production', [ProductionController::class, 'store'])->middleware('can:inventory.manage')->name('inventory.production.store');
Route::get('/inventory/production/{productionBatch}', [ProductionController::class, 'show'])->middleware('can:inventory.view')->name('inventory.production.show');
Route::post('/inventory/production/{productionBatch}/reverse', [ProductionController::class, 'reverse'])->middleware('can:inventory.manage')->name('inventory.production.reverse');

Route::get('/inventory/adjustments', [InventoryAdjustmentController::class, 'index'])->middleware('can:inventory.view')->name('inventory.adjustments.index');
Route::post('/inventory/adjustments/preview', [InventoryAdjustmentController::class, 'preview'])->middleware('can:inventory.manage')->name('inventory.adjustments.preview');
Route::post('/inventory/adjustments', [InventoryAdjustmentController::class, 'store'])->middleware('can:inventory.manage')->name('inventory.adjustments.store');
Route::get('/inventory/adjustments/{inventoryAdjustment}', [InventoryAdjustmentController::class, 'show'])->middleware('can:inventory.view')->name('inventory.adjustments.show');
Route::post('/inventory/adjustments/{inventoryAdjustment}/reverse', [InventoryAdjustmentController::class, 'reverse'])->middleware('can:inventory.manage')->name('inventory.adjustments.reverse');

Route::get('/inventory/product-consumption/{product}', [ProductInventoryConsumptionController::class, 'show'])->whereNumber('product')->middleware('can:inventory.view')->name('inventory.product-consumption.show');
Route::post('/inventory/product-consumption/{product}', [ProductInventoryConsumptionController::class, 'store'])->whereNumber('product')->middleware('can:inventory.manage')->name('inventory.product-consumption.store');

Route::get('/inventory/costing-policies', [CostingPolicyController::class, 'index'])->middleware('can:inventory.view')->name('inventory.costing-policies.index');
Route::post('/inventory/costing-policies', [CostingPolicyController::class, 'store'])->middleware('can:inventory.manage')->name('inventory.costing-policies.store');
Route::match(['put', 'patch'], '/inventory/costing-policies/{costingPolicy}', [CostingPolicyController::class, 'update'])->whereNumber('costingPolicy')->middleware('can:inventory.manage')->name('inventory.costing-policies.update');
Route::post('/inventory/costing-policies/assignments/{product}', [CostingPolicyController::class, 'assign'])->whereNumber('product')->middleware('can:inventory.manage')->name('inventory.costing-policies.assign');
Route::get('/inventory/costing', [ProductCostingController::class, 'index'])->middleware('can:inventory.view')->name('inventory.costing.index');
