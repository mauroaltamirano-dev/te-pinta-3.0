<?php

namespace App\Modules\Inventory\Queries;

use App\Modules\Inventory\Models\InventoryItem;
use Illuminate\Database\Eloquent\Collection;

final class InventoryStockBalanceQuery
{
    /** @param list<int>|null $itemIds
     * @return Collection<int, InventoryItem>
     */
    public function forItems(?array $itemIds = null): Collection
    {
        return InventoryItem::query()
            ->with('category', 'baseUnit', 'defaultInputUnit', 'presentations.contentUnit')
            ->select('inventory_items.*')
            ->selectSub(
                'SELECT COALESCE(SUM(quantity), 0) FROM inventory_stock_movements WHERE inventory_item_id = inventory_items.id',
                'stock_quantity',
            )
            ->when($itemIds !== null, fn ($query) => $query->whereKey($itemIds))
            ->orderBy('name')
            ->get();
    }
}
