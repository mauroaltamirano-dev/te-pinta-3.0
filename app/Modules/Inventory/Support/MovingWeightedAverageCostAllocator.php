<?php

namespace App\Modules\Inventory\Support;

use Illuminate\Support\Facades\DB;

/**
 * The single perpetual moving-weighted-average allocator used by every
 * Inventory physical consumption. Values are signed ledger totals, not floats.
 */
final class MovingWeightedAverageCostAllocator
{
    /**
     * @param  list<int>  $itemIds
     * @return array<int, array{basis_quantity:string,basis_cost:int,latest_quantity:?string,latest_cost:?int}>
     */
    public function valuationsFor(array $itemIds): array
    {
        $ids = array_values(array_unique(array_map('intval', $itemIds)));
        if ($ids === []) {
            return [];
        }

        $idList = implode(',', $ids);
        /** @var list<object{id:int,basis_quantity:string,basis_cost:int,latest_quantity:?string,latest_cost:?int}> $rows */
        $rows = DB::select(<<<SQL
WITH requested_items(id) AS (SELECT unnest(ARRAY[{$idList}]::bigint[]))
SELECT requested_items.id,
       COALESCE(basis.quantity, 0)::text AS basis_quantity,
       COALESCE(basis.total_cost, 0)::bigint AS basis_cost,
       latest.quantity::text AS latest_quantity,
       latest.total_cost::bigint AS latest_cost
FROM requested_items
LEFT JOIN (
    SELECT inventory_item_id, SUM(quantity) AS quantity, SUM(total_cost) AS total_cost
    FROM inventory_cost_layers WHERE inventory_item_id IN ({$idList}) GROUP BY inventory_item_id
) AS basis ON basis.inventory_item_id = requested_items.id
LEFT JOIN LATERAL (
    SELECT quantity, total_cost FROM inventory_cost_layers
    WHERE inventory_item_id = requested_items.id AND quantity > 0 AND total_cost > 0
    ORDER BY effective_date DESC, id DESC LIMIT 1
) AS latest ON true
SQL);

        return collect($rows)->mapWithKeys(static fn (object $row): array => [(int) $row->id => [
            'basis_quantity' => (string) $row->basis_quantity,
            'basis_cost' => (int) $row->basis_cost,
            'latest_quantity' => $row->latest_quantity === null ? null : (string) $row->latest_quantity,
            'latest_cost' => $row->latest_cost === null ? null : (int) $row->latest_cost,
        ]])->all();
    }

    /**
     * @param  array{basis_quantity:string,basis_cost:int,latest_quantity:?string,latest_cost:?int}  $valuation
     * @return array{string,int,int,?string}
     */
    public function allocate(string $quantity, array $valuation): array
    {
        $basisQuantity = $valuation['basis_quantity'];
        $basisCost = $valuation['basis_cost'];
        if (DecimalMath::compare($basisQuantity, '0') > 0) {
            if ($basisCost < 0) {
                return $this->latestPositiveAllocation($quantity, $valuation);
            }
            if (DecimalMath::compare($quantity, $basisQuantity) <= 0) {
                return [$basisQuantity, $basisCost, DecimalMath::roundIntegerTimesRatio($basisCost, $quantity, $basisQuantity), null];
            }

            return [$basisQuantity, $basisCost, $basisCost + $this->latestBasisCost($quantity, $basisQuantity, $valuation), 'negative_stock_latest_basis'];
        }

        return $this->latestPositiveAllocation($quantity, $valuation);
    }

    /**
     * @param  array{basis_quantity:string,basis_cost:int,latest_quantity:?string,latest_cost:?int}  $valuation
     * @return array{string,int,int,?string}
     */
    private function latestPositiveAllocation(string $quantity, array $valuation): array
    {
        if ($valuation['latest_quantity'] === null || $valuation['latest_cost'] === null) {
            return ['0.000000', 0, 0, 'no_cost_history'];
        }

        return [$valuation['latest_quantity'], $valuation['latest_cost'], $this->latestBasisCost($quantity, '0.000000', $valuation), 'negative_stock_latest_basis'];
    }

    /** @param array{basis_quantity:string,basis_cost:int,latest_quantity:?string,latest_cost:?int} $valuation */
    private function latestBasisCost(string $quantity, string $covered, array $valuation): int
    {
        if ($valuation['latest_quantity'] === null || $valuation['latest_cost'] === null) {
            return 0;
        }

        return DecimalMath::roundIntegerTimesRatio($valuation['latest_cost'], DecimalMath::subtract($quantity, $covered), $valuation['latest_quantity']);
    }
}
