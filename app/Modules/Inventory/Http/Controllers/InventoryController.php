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
use Illuminate\Support\Str;
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
        $itemFilters = $this->itemFilters($request);
        $needle = $this->normalizeName($itemFilters['search']);
        $listedItems = $items->filter(fn (InventoryItem $item): bool => ($itemFilters['category'] === null || $item->inventory_category_id === $itemFilters['category'])
            && ($itemFilters['kind'] === null || $item->kind === $itemFilters['kind'])
            && ($needle === '' || str_contains($this->normalizeName($item->name), $needle)))->values();
        $transactions = $history->paginate(20, isset($filters['transaction_id']) ? (int) $filters['transaction_id'] : null);

        return Inertia::render('admin/inventory/index', [
            'categories' => InventoryCategory::query()->orderBy('name')->get(['id', 'name', 'is_active']),
            'units' => InventoryUnit::query()->orderBy('family')->orderByDesc('is_base')->orderBy('factor_to_base')->get()
                ->map(fn (InventoryUnit $unit): array => (new InventoryUnitResource($unit))->resolve($request))->all(),
            'items' => $listedItems->map(fn (InventoryItem $item): array => (new InventoryItemResource($item))->resolve($request))->all(),
            'transactions' => [
                'data' => collect($transactions->items())->map(fn ($transaction): array => (new InventoryTransactionResource($transaction))->resolve($request))->all(),
                'current_page' => $transactions->currentPage(), 'last_page' => $transactions->lastPage(),
                'total' => $transactions->total(), 'per_page' => $transactions->perPage(),
            ],
            'filters' => ['transaction_id' => $filters['transaction_id'] ?? null, ...$itemFilters],
            'valuation' => $values['summary'],
            'valuationByCategory' => $values['by_category'],
        ]);
    }

    /**
     * Item list filters come from shareable URLs, so stale or malformed values are ignored instead of rejected.
     *
     * @return array{category: int|null, kind: string|null, search: string}
     */
    private function itemFilters(Request $request): array
    {
        $category = filter_var($request->query('category'), FILTER_VALIDATE_INT);
        $kind = $request->query('kind');
        $search = $request->query('search');

        return [
            'category' => $category !== false && InventoryCategory::query()->whereKey($category)->exists() ? $category : null,
            'kind' => in_array($kind, ['raw_material', 'semi_finished', 'finished', 'packaging', 'consumable', 'other'], true) ? $kind : null,
            'search' => is_string($search) ? Str::limit(trim($search), 100, '') : '',
        ];
    }

    private function normalizeName(string $name): string
    {
        return Str::lower(Str::ascii($name));
    }
}
