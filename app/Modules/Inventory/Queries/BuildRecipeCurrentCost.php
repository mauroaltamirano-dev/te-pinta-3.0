<?php

namespace App\Modules\Inventory\Queries;

use App\Modules\Inventory\Models\RecipeIngredient;
use App\Modules\Inventory\Models\RecipeVersion;
use App\Modules\Inventory\Support\DecimalMath;
use App\Modules\Inventory\Support\MovingWeightedAverageCostAllocator;
use App\Modules\Inventory\Support\ProductionCostProvenance;
use Illuminate\Support\Facades\DB;

/** Current estimate only; never rewrites a sealed recipe or a production fact. */
final class BuildRecipeCurrentCost
{
    public function __construct(
        private readonly MovingWeightedAverageCostAllocator $allocator,
        private readonly InventoryValuationReadModel $valuation,
        private readonly ProductionCostProvenance $provenance,
    ) {}

    /** @return array<string, mixed> */
    public function build(RecipeVersion $version): array
    {
        return $this->buildMany([$version])[$version->id];
    }

    /**
     * The five data reads are bounded regardless of recipe or ingredient count.
     * A full current physical valuation is required before exposing an exact cost.
     * Read-only callers pass `$lockItems = false`: PostgreSQL rejects row locks in a
     * READ ONLY transaction, whose snapshot already keeps the reads consistent.
     *
     * @param  iterable<RecipeVersion>  $versions
     * @return array<int, array<string, mixed>>
     */
    public function buildMany(iterable $versions, bool $lockItems = true): array
    {
        return DB::transaction(fn (): array => $this->buildLocked($versions, $lockItems));
    }

    /**
     * @param  iterable<RecipeVersion>  $versions
     * @return array<int,array<string,mixed>>
     */
    private function buildLocked(iterable $versions, bool $lockItems): array
    {
        $versions = collect($versions)->keyBy('id');
        if ($versions->isEmpty()) {
            return [];
        }

        $ingredients = RecipeIngredient::query()
            ->whereIn('recipe_version_id', $versions->keys()->all())
            ->orderBy('id')
            ->get()
            ->groupBy('recipe_version_id');
        $itemIds = array_values($ingredients->flatten(1)->pluck('inventory_item_id')
            ->map(static fn (mixed $id): int => (int) $id)->unique()->all());
        $items = DB::table('inventory_items')->whereIn('id', $itemIds)
            ->orderBy('id')->when($lockItems, fn ($query) => $query->sharedLock())->get(['id', 'tracks_stock'])->keyBy('id');
        $stocks = DB::table('inventory_stock_movements')->whereIn('inventory_item_id', $itemIds)
            ->selectRaw('inventory_item_id, COALESCE(SUM(quantity), 0)::text AS quantity')
            ->groupBy('inventory_item_id')->get()->keyBy('inventory_item_id');
        $valuations = $this->allocator->valuationsFor($itemIds);
        $warned = $this->provenance->unreliableItems($itemIds);

        $results = [];
        foreach ($versions as $version) {
            $lines = [];
            $knownCost = 0;
            $knownCount = 0;
            $unreliable = false;
            foreach ($ingredients->get($version->id, collect()) as $ingredient) {
                $itemId = (int) $ingredient->inventory_item_id;
                $basis = $valuations[$itemId] ?? [
                    'basis_quantity' => '0', 'basis_cost' => 0,
                    'latest_quantity' => null, 'latest_cost' => null,
                ];
                $stock = (string) ($stocks->get($itemId)->quantity ?? '0');
                $coverage = $this->valuation->classify(
                    (bool) ($items->get($itemId)->tracks_stock ?? false),
                    $stock,
                    $basis['basis_quantity'],
                    $basis['basis_cost'],
                )['coverage'];
                $fallback = null;
                $cost = null;
                if (isset($warned[$itemId])) {
                    $fallback = 'unreliable_input';
                    $unreliable = true;
                } elseif ($coverage === 'fully_valued') {
                    if (DecimalMath::compare((string) $ingredient->base_quantity, $stock) > 0) {
                        $fallback = 'insufficient_stock';
                    } else {
                        [, , $allocatedCost, $fallback] = $this->allocator->allocate((string) $ingredient->base_quantity, $basis);
                        if ($fallback === null) {
                            $cost = $allocatedCost;
                        }
                    }
                } elseif ($coverage === 'unknown' || $coverage === 'zero') {
                    $fallback = 'no_current_valuation';
                } else {
                    $fallback = $coverage === 'negative' ? 'negative_stock' : 'partial_valuation';
                }
                if ($cost !== null) {
                    $knownCost += $cost;
                    $knownCount++;
                } elseif (in_array($coverage, ['negative', 'partial'], true) || $fallback === 'insufficient_stock') {
                    $unreliable = true;
                }
                $lines[] = [
                    'recipe_ingredient_id' => (int) $ingredient->id,
                    'inventory_item_id' => $itemId,
                    'quantity' => (string) $ingredient->quantity,
                    'base_quantity' => (string) $ingredient->base_quantity,
                    'estimated_cost' => $cost,
                    'valuation_coverage' => $coverage,
                    'fallback' => $fallback,
                ];
            }

            $total = count($lines);
            $complete = $total > 0 && $knownCount === $total;
            $results[(int) $version->id] = [
                'status' => $complete ? 'complete' : ($unreliable ? 'unreliable' : ($knownCount > 0 ? 'partial' : 'unknown')),
                'known_cost' => $knownCost,
                'estimated_total_cost' => $complete ? $knownCost : null,
                'estimated_cost_per_output_unit' => $complete
                    ? DecimalMath::roundIntegerTimesRatio($knownCost, '1', (string) $version->expected_output_quantity)
                    : null,
                'coverage' => [
                    'known_components' => $knownCount,
                    'total_components' => $total,
                    'percentage_bps' => $total > 0 ? intdiv($knownCount * 10000, $total) : 0,
                ],
                'ingredients' => $lines,
            ];
        }

        return $results;
    }
}
