<?php

namespace App\Modules\Inventory\Queries;

use Illuminate\Support\Facades\DB;

/** Sellable products whose delivery would be blocked for missing or incomplete consumption configuration. */
final class ProductsWithoutConsumption
{
    /** @return list<string> */
    public function names(): array
    {
        return array_values(DB::table('products as p')
            ->leftJoin('inventory_product_consumption_definitions as d', 'd.product_id', '=', 'p.id')
            ->whereNull('p.deleted_at')
            ->where('p.is_available', true)
            ->where(fn ($query) => $query->whereNull('d.id')->orWhere(fn ($incomplete) => $incomplete->where('d.mode', 'consume')->whereNull('d.current_version_id')))
            ->orderBy('p.name')
            ->pluck('p.name')
            ->map(fn ($name): string => (string) $name)
            ->all());
    }
}
