<?php

namespace App\Modules\Inventory\Http\Controllers;

use App\Modules\Inventory\Actions\CreateInventoryAdjustment;
use App\Modules\Inventory\Actions\PreviewInventoryAdjustment;
use App\Modules\Inventory\Actions\ReverseInventoryAdjustment;
use App\Modules\Inventory\Data\InventoryAdjustmentData;
use App\Modules\Inventory\Http\Requests\ReverseInventoryAdjustmentRequest;
use App\Modules\Inventory\Http\Requests\StoreInventoryAdjustmentRequest;
use App\Modules\Inventory\Models\InventoryAdjustment;
use App\Modules\Inventory\Models\InventoryItem;
use App\Modules\Inventory\Models\InventoryUnit;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

final class InventoryAdjustmentController
{
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', InventoryItem::class);

        return Inertia::render('admin/inventory/adjustments/index', ['adjustments' => InventoryAdjustment::query()->with('item.baseUnit', 'unit', 'reversal', 'creator')->latest('id')->paginate(20), 'items' => InventoryItem::query()->where('tracks_stock', true)->where('is_active', true)->with('baseUnit', 'category')->orderBy('name')->get(), 'units' => InventoryUnit::query()->get(), 'business_today' => CarbonImmutable::today(config('orders.timezone'))->toDateString()]);
    }

    public function preview(StoreInventoryAdjustmentRequest $r, PreviewInventoryAdjustment $action): JsonResponse
    {
        return response()->json(['data' => $action->execute($this->data($r->validated()))]);
    }

    public function store(StoreInventoryAdjustmentRequest $r, CreateInventoryAdjustment $action): RedirectResponse
    {
        $a = $action->execute($this->data($r->validated()), $r->user()->id);

        return to_route('admin.inventory.adjustments.show', $a);
    }

    public function show(InventoryAdjustment $inventoryAdjustment): Response
    {
        Gate::authorize('viewAny', InventoryItem::class);

        return Inertia::render('admin/inventory/adjustments/show', ['adjustment' => $inventoryAdjustment->load('item.baseUnit', 'unit', 'creator', 'transaction.movements.costLayer', 'reversal', 'reversalOf'), 'business_today' => CarbonImmutable::today(config('orders.timezone'))->toDateString()]);
    }

    public function reverse(ReverseInventoryAdjustmentRequest $r, InventoryAdjustment $inventoryAdjustment, ReverseInventoryAdjustment $action): RedirectResponse
    {
        $v = $r->validated();
        $action->execute($inventoryAdjustment->id, CarbonImmutable::parse($v['effective_date']), $v['note'], $r->user()->id);

        return to_route('admin.inventory.adjustments.show', $inventoryAdjustment);
    }

    /** @param array<string,mixed> $v */
    private function data(array $v): InventoryAdjustmentData
    {
        return new InventoryAdjustmentData((int) $v['inventory_item_id'], (int) $v['unit_id'], (string) $v['type'], (string) $v['quantity'], CarbonImmutable::parse($v['effective_date']), (string) $v['idempotency_key'], isset($v['explicit_total_cost']) ? (int) $v['explicit_total_cost'] : null, $v['note'] ?? null);
    }
}
