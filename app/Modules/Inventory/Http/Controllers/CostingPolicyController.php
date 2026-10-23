<?php

namespace App\Modules\Inventory\Http\Controllers;

use App\Modules\Identity\Enums\Permission;
use App\Modules\Inventory\Actions\AssignProductCostingPolicy;
use App\Modules\Inventory\Actions\ConfigureCostingPolicy;
use App\Modules\Inventory\Models\CostingPolicy;
use App\Modules\Inventory\Models\InventoryItem;
use App\Modules\Inventory\Models\InventoryUnit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

final class CostingPolicyController
{
    public function index(): Response
    {
        Gate::authorize(Permission::InventoryView->value);

        return Inertia::render('admin/inventory/costing-policies/index', [
            'policies' => CostingPolicy::query()->with('presentations.components.item', 'presentations.components.unit')->orderBy('name')->get(),
            'items' => InventoryItem::query()->where('is_active', true)->where('tracks_stock', true)->whereIn('kind', ['packaging', 'consumable'])->orderBy('name')->get(['id', 'name', 'unit_family', 'default_input_unit_id']),
            'units' => InventoryUnit::query()->orderBy('id')->get(['id', 'code', 'name', 'family']),
        ]);
    }

    public function store(Request $request, ConfigureCostingPolicy $action): RedirectResponse
    {
        Gate::authorize(Permission::InventoryManage->value);
        $action->execute(null, $request->all());

        return to_route('admin.inventory.costing-policies.index')->with('success', 'Packaging y gastos guardados.');
    }

    public function update(Request $request, int $costingPolicy, ConfigureCostingPolicy $action): RedirectResponse
    {
        Gate::authorize(Permission::InventoryManage->value);
        $action->execute($costingPolicy, $request->all());

        return to_route('admin.inventory.costing-policies.index')->with('success', 'Packaging y gastos guardados.');
    }

    public function assign(Request $request, int $product, AssignProductCostingPolicy $action): RedirectResponse
    {
        Gate::authorize(Permission::InventoryManage->value);
        $v = $request->validate(['costing_policy_id' => ['present', 'nullable', 'integer']]);
        $action->execute($product, $v['costing_policy_id'] === null ? null : (int) $v['costing_policy_id']);

        return to_route('admin.inventory.costing.index', ['product_id' => $product]);
    }
}
