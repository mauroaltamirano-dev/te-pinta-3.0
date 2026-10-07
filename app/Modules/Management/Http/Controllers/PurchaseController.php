<?php

namespace App\Modules\Management\Http\Controllers;

use App\Modules\Inventory\Http\Resources\InventoryItemResource;
use App\Modules\Inventory\Http\Resources\InventoryUnitResource;
use App\Modules\Inventory\Models\InventoryItem;
use App\Modules\Inventory\Models\InventoryUnit;
use App\Modules\Inventory\Queries\InventoryStockBalanceQuery;
use App\Modules\Management\Actions\CreatePurchase;
use App\Modules\Management\Actions\ReversePurchase;
use App\Modules\Management\Data\PurchaseData;
use App\Modules\Management\Data\ReversalData;
use App\Modules\Management\Http\Requests\ReversePurchaseRequest;
use App\Modules\Management\Http\Requests\StorePurchaseRequest;
use App\Modules\Management\Http\Resources\PurchaseResource;
use App\Modules\Management\Models\Purchase;
use App\Modules\Management\Models\Wallet;
use App\Modules\Management\Queries\PurchaseListQuery;
use App\Modules\Orders\Support\OrderBusinessDate;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

final class PurchaseController
{
    public function index(Request $request, PurchaseListQuery $list, InventoryStockBalanceQuery $stock, OrderBusinessDate $businessDate): Response
    {
        Gate::authorize('viewAny', Purchase::class);
        $filters = $request->validate([
            'from' => ['nullable', 'date_format:Y-m-d'], 'to' => ['nullable', 'date_format:Y-m-d'],
            'wallet_id' => ['nullable', 'integer', Rule::exists('wallets', 'id')],
            'supplier' => ['nullable', 'string', 'max:160'], 'search' => ['nullable', 'string', 'max:160'],
            'status' => ['nullable', Rule::in(['confirmed', 'reversed'])],
        ]);
        $purchases = $list->paginate($request);

        return Inertia::render('admin/management/purchases/index', [
            ...$this->formOptions($request, $stock, $businessDate),
            'filters' => $filters,
            'wallets' => Wallet::query()->orderBy('sort_order')->orderBy('id')->get(['id', 'name', 'archived_at']),
            'purchases' => ['data' => collect($purchases->items())->map(fn (Purchase $purchase): array => (new PurchaseResource($purchase))->resolve($request))->all(),
                'current_page' => $purchases->currentPage(), 'last_page' => $purchases->lastPage(),
                'per_page' => $purchases->perPage(), 'total' => $purchases->total()],
        ]);
    }

    public function create(Request $request, InventoryStockBalanceQuery $stock, OrderBusinessDate $businessDate): Response
    {
        Gate::authorize('create', Purchase::class);

        return Inertia::render('admin/management/purchases/create', $this->formOptions($request, $stock, $businessDate));
    }

    public function store(StorePurchaseRequest $request, CreatePurchase $create): RedirectResponse
    {
        $purchase = $create->execute(PurchaseData::fromRequest($request), (int) $request->user()->getAuthIdentifier());

        return redirect()->route('admin.management.purchases.show', $purchase)->with('success', 'Compra registrada.');
    }

    public function show(Request $request, Purchase $purchase): Response
    {
        Gate::authorize('view', $purchase);
        $purchase->load(['wallet', 'creator', 'lines', 'financialTransaction.entries.wallet',
            'inventoryTransaction.movements.item.baseUnit', 'inventoryTransaction.costLayers', 'reversal']);
        $purchase->financialTransaction->loadCount('attachments');

        return Inertia::render('admin/management/purchases/show', [
            'business_today' => app(OrderBusinessDate::class)->today()->toDateString(),
            'purchase' => (new PurchaseResource($purchase))->resolve($request),
        ]);
    }

    public function reverse(ReversePurchaseRequest $request, Purchase $purchase, ReversePurchase $reverse): RedirectResponse
    {
        $reverse->execute($purchase, ReversalData::fromRequest($request), (int) $request->user()->getAuthIdentifier());

        return back()->with('success', 'Compra revertida.');
    }

    /** @return array<string, mixed> */
    private function formOptions(Request $request, InventoryStockBalanceQuery $stock, OrderBusinessDate $businessDate): array
    {
        $items = $stock->forItems()->load(['category', 'baseUnit', 'defaultInputUnit', 'presentations.contentUnit'])
            ->filter(fn (InventoryItem $item): bool => $item->is_active)->values();

        return [
            'business_today' => $businessDate->today()->toDateString(),
            'wallets' => Wallet::query()->whereNull('archived_at')->orderBy('sort_order')->orderBy('id')->get(['id', 'name', 'archived_at']),
            'inventory_items' => $items->map(fn (InventoryItem $item): array => (new InventoryItemResource($item))->resolve($request))->all(),
            'units' => InventoryUnit::query()->orderBy('family')->orderByDesc('is_base')->orderBy('factor_to_base')->get()
                ->map(fn (InventoryUnit $unit): array => (new InventoryUnitResource($unit))->resolve($request))->all(),
        ];
    }
}
