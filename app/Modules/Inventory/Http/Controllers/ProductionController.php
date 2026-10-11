<?php

namespace App\Modules\Inventory\Http\Controllers;

use App\Modules\Inventory\Actions\CreateProductionBatch;
use App\Modules\Inventory\Actions\ReverseProductionBatch;
use App\Modules\Inventory\Data\ProductionBatchData;
use App\Modules\Inventory\Http\Requests\ReverseProductionBatchRequest;
use App\Modules\Inventory\Http\Requests\StoreProductionBatchRequest;
use App\Modules\Inventory\Models\InventoryItem;
use App\Modules\Inventory\Models\InventoryUnit;
use App\Modules\Inventory\Models\ProductionBatch;
use App\Modules\Inventory\Models\RecipeVersion;
use App\Modules\Inventory\Queries\BuildRecipeCurrentCost;
use App\Modules\Inventory\Support\DecimalMath;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

final class ProductionController
{
    public function index(): Response
    {
        Gate::authorize('viewAny', InventoryItem::class);

        return Inertia::render('admin/inventory/production/index', ['batches' => ProductionBatch::query()->with('recipeVersion.recipe', 'outputItem.baseUnit', 'reversal')->latest('id')->paginate(20)]);
    }

    public function create(Request $request, BuildRecipeCurrentCost $cost): Response
    {
        Gate::authorize('create', InventoryItem::class);
        $versions = RecipeVersion::query()->where('is_active', true)->where('is_sealed', true)->whereHas('recipe', fn ($q) => $q->where('is_active', true))
            ->when($request->filled('recipe'), fn ($q) => $q->where('recipe_id', $request->integer('recipe')))
            ->with('recipe', 'outputItem.baseUnit', 'outputUnit', 'ingredients.item.baseUnit', 'ingredients.unit')->orderBy('recipe_id')->get();
        $estimates = $cost->buildMany($versions);
        foreach ($versions as $v) {
            $v->setAttribute('current_cost', $estimates[$v->id]);
        }

        return Inertia::render('admin/inventory/production/create', ['versions' => $versions, 'units' => InventoryUnit::query()->get(), 'business_today' => now()->toDateString()]);
    }

    public function store(StoreProductionBatchRequest $request, CreateProductionBatch $action): RedirectResponse
    {
        $v = $request->validated();
        $batch = $action->execute(new ProductionBatchData((int) $v['recipe_version_id'], (int) $v['output_unit_id'], (string) $v['actual_output_quantity'], CarbonImmutable::parse($v['effective_date']), (string) $v['idempotency_key'], $v['notes'] ?? null), $request->user()->id);

        return to_route('admin.inventory.production.show', $batch);
    }

    public function show(ProductionBatch $productionBatch): Response
    {
        Gate::authorize('viewAny', InventoryItem::class);
        $productionBatch->load('recipeVersion.recipe', 'recipeVersion.outputUnit', 'outputItem.baseUnit', 'transaction.movements.item.baseUnit', 'transaction.movements.costLayer', 'allocations', 'reversal');
        $productionBatch->setAttribute('historical_cost_per_output_unit', $productionBatch->total_cost === null ? null : DecimalMath::roundIntegerTimesRatio($productionBatch->total_cost, '1', $productionBatch->actual_output_quantity));
        $productionBatch->setAttribute('output_unit', InventoryUnit::query()->findOrFail($productionBatch->output_unit_id));

        return Inertia::render('admin/inventory/production/show', ['batch' => $productionBatch, 'business_today' => now()->toDateString()]);
    }

    public function reverse(ReverseProductionBatchRequest $request, ProductionBatch $productionBatch, ReverseProductionBatch $action): RedirectResponse
    {
        $v = $request->validated();
        $action->execute($productionBatch->id, CarbonImmutable::parse($v['effective_date']), $v['reason'], $request->user()->id);

        return to_route('admin.inventory.production.show', $productionBatch);
    }
}
