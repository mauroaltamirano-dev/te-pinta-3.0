<?php

namespace App\Modules\Inventory\Http\Controllers;

use App\Modules\Inventory\Http\Resources\InventoryItemResource;
use App\Modules\Inventory\Http\Resources\InventoryTransactionResource;
use App\Modules\Inventory\Http\Resources\InventoryUnitResource;
use App\Modules\Inventory\Models\InventoryCategory;
use App\Modules\Inventory\Models\InventoryItem;
use App\Modules\Inventory\Models\InventoryUnit;
use App\Modules\Inventory\Queries\InventoryMovementHistoryQuery;
use App\Modules\Inventory\Queries\InventoryStockBalanceQuery;
use App\Modules\Inventory\Queries\InventoryValuationReadModel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

final class InventoryController
{
    public function index(Request $request, InventoryStockBalanceQuery $stock, InventoryMovementHistoryQuery $history, InventoryValuationReadModel $valuation): Response
    {
        Gate::authorize('viewAny', InventoryItem::class);
        $filters = $request->validate(['transaction_id' => ['nullable', 'integer', Rule::exists('inventory_transactions', 'id')]]);
        $items = $stock->forItems();
        $values = $valuation->summarize($items);
        foreach ($items as $item) {
            $item->setAttribute('valuation', $values['by_item'][$item->id]);
        }
        $transactions = $history->paginate(20, isset($filters['transaction_id']) ? (int) $filters['transaction_id'] : null);

        return Inertia::render('admin/inventory/index', [
            'categories' => InventoryCategory::query()->orderBy('name')->get(['id', 'name', 'is_active']),
            'units' => InventoryUnit::query()->orderBy('family')->orderByDesc('is_base')->orderBy('factor_to_base')->get()
                ->map(fn (InventoryUnit $unit): array => (new InventoryUnitResource($unit))->resolve($request))->all(),
            'items' => $items->map(fn (InventoryItem $item): array => (new InventoryItemResource($item))->resolve($request))->all(),
            'transactions' => [
                'data' => collect($transactions->items())->map(fn ($transaction): array => (new InventoryTransactionResource($transaction))->resolve($request))->all(),
                'current_page' => $transactions->currentPage(), 'last_page' => $transactions->lastPage(),
                'total' => $transactions->total(), 'per_page' => $transactions->perPage(),
            ],
            'filters' => ['transaction_id' => $filters['transaction_id'] ?? null],
            'valuation' => $values['summary'],
            'valuationByCategory' => $values['by_category'],
        ]);
    }
}
