<?php

namespace App\Modules\Inventory\Queries;

use App\Modules\Inventory\Models\InventoryItem;
use App\Modules\Inventory\Support\DecimalMath;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/** Derived current valuation only; historical point-in-time valuation is not inferred. */
final class InventoryValuationReadModel
{
    /** @param Collection<int, InventoryItem> $items
     * @return array{summary:array<string,int>,by_item:array<int,array{coverage:string,value:int|null,cost_quantity:string,cost_value:int}>,by_category:list<array{id:int,name:string,confident_value:int,fully_valued:int,partial_or_unknown:int,negative:int}>}
     */
    public function summarize(Collection $items): array
    {
        $ids = [];
        foreach ($items as $item) {
            $ids[] = (int) $item->id;
        }
        if ($ids === []) {
            return ['summary' => ['fully_valued' => 0, 'partial' => 0, 'unknown' => 0, 'zero' => 0, 'negative' => 0, 'unreliable' => 0, 'confident_value' => 0], 'by_item' => [], 'by_category' => []];
        }
        $costs = DB::table('inventory_cost_layers')
            ->whereIn('inventory_item_id', $ids)
            ->selectRaw('inventory_item_id, COALESCE(SUM(quantity), 0) AS cost_quantity, COALESCE(SUM(total_cost), 0) AS cost_value, COALESCE(SUM(quantity) FILTER (WHERE valuation_warning IS NOT NULL), 0) AS warning_quantity')
            ->groupBy('inventory_item_id')->get()->keyBy('inventory_item_id');
        $byItem = [];
        $summary = ['fully_valued' => 0, 'partial' => 0, 'unknown' => 0, 'zero' => 0, 'negative' => 0, 'unreliable' => 0, 'confident_value' => 0];
        foreach ($items as $item) {
            $stock = (string) ($item->stock_quantity ?? '0');
            $cost = $costs->get($item->id);
            $costQuantity = (string) ($cost->cost_quantity ?? '0');
            $costValue = (int) ($cost->cost_value ?? 0);
            ['coverage' => $coverage, 'value' => $value] = $this->classify($item->tracks_stock, $stock, $costQuantity, $costValue);
            if ($coverage === 'fully_valued' && DecimalMath::compare((string) ($cost->warning_quantity ?? '0'), '0') > 0) {
                $coverage = 'unreliable';
                $value = null;
            }
            if ($coverage === 'fully_valued') {
                $summary['confident_value'] += $value;
            }
            $summary[$coverage]++;
            $byItem[(int) $item->id] = ['coverage' => $coverage, 'value' => $value, 'cost_quantity' => $costQuantity, 'cost_value' => $costValue];
        }

        $categories = [];
        foreach ($items as $item) {
            $id = (int) $item->inventory_category_id;
            $categories[$id] ??= ['id' => $id, 'name' => $item->category->name, 'confident_value' => 0, 'fully_valued' => 0, 'partial_or_unknown' => 0, 'negative' => 0];
            $coverage = $byItem[(int) $item->id]['coverage'];
            if ($coverage === 'fully_valued') {
                $categories[$id]['fully_valued']++;
                $categories[$id]['confident_value'] += $byItem[(int) $item->id]['value'] ?? 0;
            } elseif ($coverage === 'negative') {
                $categories[$id]['negative']++;
            } elseif (in_array($coverage, ['partial', 'unknown', 'unreliable'], true)) {
                $categories[$id]['partial_or_unknown']++;
            }
        }

        return ['summary' => $summary, 'by_item' => $byItem, 'by_category' => array_values($categories)];
    }

    /** @return array{coverage:'fully_valued'|'partial'|'unknown'|'zero'|'negative',value:int|null} */
    public function classify(bool $tracksStock, string $stockQuantity, string $costQuantity, int $costValue): array
    {
        if (! $tracksStock || DecimalMath::compare($stockQuantity, '0') === 0) {
            return ['coverage' => 'zero', 'value' => 0];
        }
        if (DecimalMath::compare($stockQuantity, '0') < 0) {
            return ['coverage' => 'negative', 'value' => null];
        }
        if (DecimalMath::compare($costQuantity, '0') === 0) {
            return ['coverage' => 'unknown', 'value' => null];
        }
        if (DecimalMath::compare($stockQuantity, $costQuantity) === 0) {
            return ['coverage' => 'fully_valued', 'value' => $costValue];
        }

        return ['coverage' => 'partial', 'value' => null];
    }
}
