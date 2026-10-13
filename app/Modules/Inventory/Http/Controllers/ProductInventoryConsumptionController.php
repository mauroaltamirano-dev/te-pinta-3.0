<?php

namespace App\Modules\Inventory\Http\Controllers;

use App\Modules\Inventory\Actions\ConfigureProductInventoryConsumption;
use App\Modules\Inventory\Http\Requests\ConfigureProductConsumptionRequest;
use App\Modules\Inventory\Models\InventoryItem;
use App\Modules\Inventory\Models\InventoryUnit;
use App\Modules\Inventory\Models\ProductInventoryConsumptionDefinition;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

final class ProductInventoryConsumptionController
{
    public function show(int $product): Response
    {
        Gate::authorize('administrator');
        $p = DB::table('products')->where('id', $product)->whereNull('deleted_at')->first();
        abort_if($p === null, 404);

        return Inertia::render('admin/inventory/product-consumption', ['product' => ['id' => $p->id, 'name' => $p->name], 'definition' => ProductInventoryConsumptionDefinition::query()->where('product_id', $product)->with('currentVersion.components.item.baseUnit', 'versions.components.item.baseUnit', 'versions.components.unit')->first(), 'items' => InventoryItem::query()->where('is_active', true)->where('tracks_stock', true)->where('order_consumption_enabled', true)->with('baseUnit', 'category')->orderBy('name')->get(), 'units' => InventoryUnit::query()->orderBy('id')->get()]);
    }

    public function store(ConfigureProductConsumptionRequest $request, int $product, ConfigureProductInventoryConsumption $action): RedirectResponse
    {
        $v = $request->validated();
        $action->execute($product, $v['mode'], $v['components'], $request->user()->id);

        return to_route('admin.inventory.product-consumption.show', $product);
    }
}
