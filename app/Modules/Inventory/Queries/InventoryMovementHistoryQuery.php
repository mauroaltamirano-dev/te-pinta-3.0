<?php

namespace App\Modules\Inventory\Queries;

use App\Modules\Inventory\Models\InventoryTransaction;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

final class InventoryMovementHistoryQuery
{
    /** @return LengthAwarePaginator<int, InventoryTransaction> */
    public function paginate(int $perPage = 20, ?int $transactionId = null): LengthAwarePaginator
    {
        return InventoryTransaction::query()
            ->with('movements.item.category', 'movements.unit', 'movements.costLayer', 'creator')
            ->when($transactionId !== null, fn ($query) => $query->whereKey($transactionId))
            ->orderByDesc('effective_date')
            ->orderByDesc('id')
            ->paginate($perPage);
    }
}
