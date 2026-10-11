<?php

namespace App\Modules\Inventory\Support;

use Illuminate\Support\Facades\DB;

/** Signed warning quantities propagate uncertainty without rewriting past receipts. */
final class ProductionCostProvenance
{
    /**
     * @param  list<int>  $ids
     * @return array<int,bool>
     */
    public function unreliableItems(array $ids): array
    {
        return DB::table('inventory_cost_layers')->whereIn('inventory_item_id', $ids)
            ->whereNotNull('valuation_warning')->select('inventory_item_id')
            ->groupBy('inventory_item_id')->havingRaw('SUM(quantity)>0')->pluck('inventory_item_id')
            ->mapWithKeys(static fn ($id): array => [(int) $id => true])->all();
    }
}
