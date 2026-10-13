<?php

namespace App\Modules\Inventory\Http\Controllers;

use App\Modules\Inventory\Models\CostingPolicy;
use App\Modules\Inventory\Queries\BuildProductUnitEconomics;
use App\Modules\Products\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

final class ProductCostingController
{
    public function index(Request $request, BuildProductUnitEconomics $builder): Response
    {
        Gate::authorize('administrator');
        $v = $request->validate(['product_id' => ['nullable', 'integer'], 'quantity' => ['nullable', 'integer', 'between:1,120']]);
        $product = isset($v['product_id']) ? Product::query()->whereKey((int) $v['product_id'])->firstOrFail() : null;

        return Inertia::render('admin/inventory/costing/index', [
            'products' => Product::query()->orderBy('name')->get(['id', 'name', 'pricing_family']),
            'selectedProductId' => $product?->id,
            'economics' => $product === null ? null : $builder->build($product, (int) ($v['quantity'] ?? 1)),
            'policies' => CostingPolicy::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']),
        ]);
    }
}
