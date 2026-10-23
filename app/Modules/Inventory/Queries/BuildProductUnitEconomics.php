<?php

namespace App\Modules\Inventory\Queries;

use App\Modules\Inventory\Models\CostingPolicy;
use App\Modules\Inventory\Models\ProductInventoryConsumptionDefinition;
use App\Modules\Inventory\Models\RecipeVersion;
use App\Modules\Inventory\Support\DecimalMath;
use App\Modules\Orders\Enums\OrderCondition;
use App\Modules\Orders\Pricing\OrderPricingEngine;
use App\Modules\Products\Enums\ProductPricingFamily;
use App\Modules\Products\Models\Product;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use stdClass;

/**
 * Read-only current estimates. No stock allocation or historical writes. Recipes are
 * expanded only as a fallback for a semi-finished component without reliable valuation.
 */
final readonly class BuildProductUnitEconomics
{
    public function __construct(
        private InventoryValuationReadModel $valuation,
        private OrderPricingEngine $pricing,
        private BuildRecipeCurrentCost $recipeCost,
        private ProductCostingNextSteps $nextSteps,
    ) {}

    /** @return array<string, mixed> */
    public function build(Product $product, int $unitQuantity = 1): array
    {
        // A standalone read uses one stable PostgreSQL snapshot across configuration,
        // valuation and backend quote. An existing caller transaction owns its isolation.
        if (DB::transactionLevel() === 0) {
            return DB::transaction(function () use ($product, $unitQuantity): array {
                DB::statement('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ READ ONLY');

                return $this->read($product, $unitQuantity);
            });
        }

        return $this->read($product, $unitQuantity);
    }

    /** @return array<string, mixed> */
    private function read(Product $product, int $unitQuantity): array
    {
        $product = Product::query()->whereKey($product->id)->firstOrFail();
        if ($unitQuantity < 1 || $unitQuantity > 120 || ($product->pricing_family === ProductPricingFamily::Empanada && $unitQuantity !== 1)) {
            throw ValidationException::withMessages(['quantity' => 'Elegí entre 1 y 120 unidades para un producto por unidad; las empanadas se calculan con sus presentaciones estándar.']);
        }
        $d = ProductInventoryConsumptionDefinition::query()->where('product_id', $product->id)->with('currentVersion')->first();
        $physical = $d->mode ?? 'missing_config';
        $direct = collect();
        if ($d?->mode === 'consume') {
            $version = $d->currentVersion;
            if ($version !== null && $version->is_active && $version->is_sealed) {
                $direct = DB::table('inventory_product_consumption_components as c')->join('inventory_items as i', 'i.id', '=', 'c.inventory_item_id')->join('inventory_units as u', 'u.id', '=', 'c.unit_id')->where('c.version_id', $version->id)->orderBy('c.id')->get(['c.inventory_item_id', 'c.quantity', 'c.base_quantity', 'i.name as item_name', 'u.code as unit_code']);
            }
            if ($direct->isEmpty()) {
                $physical = 'missing_config';
            }
        }
        $policyId = DB::table('inventory_product_costing_policies')->where('product_id', $product->id)->value('costing_policy_id');
        $policy = $policyId === null ? null : CostingPolicy::query()->with('presentations.components.item', 'presentations.components.unit')->whereKey((int) $policyId)->first();
        $policyStatus = $policy === null ? 'missing' : ($policy->is_active ? 'active' : 'inactive');
        $packaging = collect();
        if ($policyStatus === 'active') {
            foreach ($policy->presentations as $presentation) {
                foreach ($presentation->components as $c) {
                    $packaging->push((object) ['inventory_item_id' => $c->inventory_item_id, 'quantity' => $c->quantity, 'base_quantity' => $c->base_quantity, 'item_name' => $c->item->name, 'unit_code' => $c->unit->code, 'commercial_quantity' => $presentation->commercial_quantity]);
                }
            }
        }
        $ids = $direct->pluck('inventory_item_id')->merge($packaging->pluck('inventory_item_id'))->unique()->map(fn ($id): int => (int) $id)->values()->all();
        $current = $this->currentValuations(array_values($ids));
        $recipes = $this->recipeEstimates($direct, $current);
        $modes = $product->pricing_family === ProductPricingFamily::Empanada ? [1 => 'unit', 6 => 'half_dozen', 12 => 'dozen'] : [$unitQuantity => 'unit'];
        $presentations = [];
        foreach ($modes as $q => $mode) {
            $rule = $policyStatus === 'active' ? $policy->presentations->firstWhere('commercial_quantity', $q) : null;
            $packagingStatus = $rule->packaging_mode ?? 'missing';
            $presentationPackaging = $packaging->where('commercial_quantity', $q);
            $required = $this->requiredQuantities($direct, $q, $presentationPackaging);
            $dl = $this->costLines($direct, $q, $current, false, $required, $recipes);
            $pl = $this->costLines($presentationPackaging, 1, $current, true, $required, []);
            $dc = $this->coverage($dl, $physical !== 'missing_config');
            $pc = $this->coverage($pl, $rule !== null);
            $knownDirect = $this->sumCost($dl);
            $knownPackaging = $this->sumCost($pl);
            $directCost = $dc['status'] === 'complete' ? $knownDirect : null;
            $packagingCost = $pc['status'] === 'complete' ? $knownPackaging : null;
            $costComplete = $directCost !== null && $packagingCost !== null && $policyStatus === 'active';
            $overhead = $costComplete ? DecimalMath::roundIntegerTimesRatio($directCost + $packagingCost, (string) $policy->overhead_basis_points, '10000') : null;
            $total = $costComplete ? $directCost + $packagingCost + $overhead : null;
            $price = $this->quotedPrice($product, $q, $mode);
            $margin = $total !== null && $price !== null ? $price - $total : null;
            $warnings = [];
            if ($physical === 'missing_config') {
                $warnings[] = 'consumption_missing';
            }
            if ($policyStatus !== 'active') {
                $warnings[] = 'policy_'.$policyStatus;
            } elseif ($rule === null) {
                $warnings[] = 'packaging_missing';
            }
            if ($physical !== 'missing_config' && $dc['status'] !== 'complete') {
                $warnings[] = 'direct_cost_'.$dc['status'];
            }
            if ($rule !== null && $pc['status'] !== 'complete') {
                $warnings[] = 'packaging_cost_'.$pc['status'];
            }
            if ($price === null) {
                $warnings[] = 'pricing_unavailable';
            }
            $status = in_array('unreliable', [$dc['status'], $pc['status']], true) ? 'unreliable' : ($costComplete && $price !== null ? 'complete' : 'incomplete');
            $presentations[] = [
                'commercial_quantity' => $q, 'pricing_mode' => $mode, 'selling_price' => $price, 'status' => $status, 'complete' => $status === 'complete',
                'direct_cost' => $directCost, 'known_direct_cost' => $knownDirect, 'direct_coverage' => $dc, 'packaging_status' => $packagingStatus,
                'packaging_cost' => $packagingCost, 'known_packaging_cost' => $knownPackaging, 'packaging_coverage' => $pc,
                'overhead_estimate' => $overhead, 'estimated_total_cost' => $total, 'estimated_margin' => $margin,
                'estimated_margin_percentage_bps' => $margin !== null && $price > 0 ? DecimalMath::roundIntegerTimesRatio($margin, '10000', (string) $price) : null,
                'warnings' => $warnings, 'estimated_by_recipe' => in_array('recipe', array_column($dl, 'cost_source'), true),
                'direct_components' => $this->publicLines($dl), 'packaging_components' => $this->publicLines($pl),
            ];
        }
        $recipeItems = [];
        foreach ($presentations as $presentation) {
            foreach ($presentation['direct_components'] as $line) {
                if ($line['cost_source'] === 'recipe' && ! in_array($line['item_name'], $recipeItems, true)) {
                    $recipeItems[] = $line['item_name'];
                }
            }
        }
        $economics = ['product_id' => $product->id, 'product_name' => $product->name, 'pricing_family' => $product->pricing_family->value, 'condition' => 'cooked', 'physical_status' => $physical, 'policy_status' => $policyStatus, 'costing_policy' => $policy === null ? null : ['id' => $policy->id, 'name' => $policy->name, 'overhead_basis_points' => $policy->overhead_basis_points], 'presentations' => $presentations, 'recipe_estimated_items' => $recipeItems];

        return $economics + ['next_steps' => $this->nextSteps->build($economics, $recipes)];
    }

    /**
     * Current recipe estimate for each semi-finished direct component whose own
     * valuation is not reliable. Only one active recipe may back an item.
     *
     * @param  Collection<int, stdClass>  $direct
     * @param  Collection<int, stdClass>  $current
     * @return array<int, array{status:'complete'|'incomplete'|'missing'|'ambiguous',total_cost:?int,output_base_quantity:?string,missing:list<array{name:string,kind:?string,fallback:?string}>}>
     */
    private function recipeEstimates(Collection $direct, Collection $current): array
    {
        $ids = [];
        foreach ($direct as $component) {
            $item = $current->get((int) $component->inventory_item_id);
            if ($item->kind === 'semi_finished' && $this->valuationOf($item)['warning'] !== null) {
                $ids[] = (int) $item->id;
            }
        }
        if ($ids === []) {
            return [];
        }
        $versions = RecipeVersion::query()->whereIn('output_inventory_item_id', $ids)->where('is_active', true)->where('is_sealed', true)
            ->whereHas('recipe', fn ($query) => $query->where('is_active', true))->orderBy('id')->get()->groupBy('output_inventory_item_id');
        $single = $versions->filter(fn (Collection $candidates): bool => $candidates->count() === 1)->map(fn (Collection $candidates): RecipeVersion => $candidates->first());
        $estimates = $this->recipeCost->buildMany($single->values(), lockItems: false);
        $ingredientIds = [];
        foreach ($estimates as $estimate) {
            foreach ($estimate['ingredients'] as $ingredient) {
                $ingredientIds[] = $ingredient['inventory_item_id'];
            }
        }
        $ingredients = DB::table('inventory_items')->whereIn('id', array_values(array_unique($ingredientIds)))->get(['id', 'name', 'kind'])->keyBy('id');
        $result = [];
        foreach ($ids as $id) {
            $candidates = $versions->get($id);
            if ($candidates === null || $candidates->count() > 1) {
                $result[$id] = ['status' => $candidates === null ? 'missing' : 'ambiguous', 'total_cost' => null, 'output_base_quantity' => null, 'missing' => []];

                continue;
            }
            $version = $candidates->first();
            $estimate = $estimates[$version->id];
            $missing = [];
            foreach ($estimate['ingredients'] as $ingredient) {
                if ($ingredient['estimated_cost'] === null) {
                    $item = $ingredients->get($ingredient['inventory_item_id']);
                    $missing[] = ['name' => (string) $item->name, 'kind' => $item->kind, 'fallback' => $ingredient['fallback']];
                }
            }
            $complete = $estimate['estimated_total_cost'] !== null;
            $result[$id] = ['status' => $complete ? 'complete' : 'incomplete', 'total_cost' => $estimate['estimated_total_cost'], 'output_base_quantity' => (string) $version->expected_output_base_quantity, 'missing' => $missing];
        }

        return $result;
    }

    /** @return array{coverage:string,warning:?string} */
    private function valuationOf(stdClass $item): array
    {
        $coverage = $this->valuation->classify((bool) $item->tracks_stock, (string) $item->stock_quantity, (string) $item->basis_quantity, (int) $item->basis_cost)['coverage'];
        if ($coverage === 'fully_valued' && DecimalMath::compare((string) $item->warning_quantity, '0') > 0) {
            $coverage = 'unreliable';
        }

        return ['coverage' => $coverage, 'warning' => match ($coverage) {
            'fully_valued' => null,'negative' => 'negative_stock','partial' => 'partial_valuation','unreliable' => 'unreliable_valuation',default => 'no_current_valuation'
        }];
    }

    /** @param list<int> $ids
     * @return Collection<int, stdClass> */
    private function currentValuations(array $ids): Collection
    {
        if ($ids === []) {
            return collect();
        }
        // Both physical and economic aggregates are read by the same statement;
        // valuation warning layers cannot be lost by calling classify alone.
        $stocks = DB::table('inventory_stock_movements')->whereIn('inventory_item_id', $ids)->selectRaw('inventory_item_id, SUM(quantity) AS stock_quantity')->groupBy('inventory_item_id');
        $costs = DB::table('inventory_cost_layers')->whereIn('inventory_item_id', $ids)->selectRaw('inventory_item_id, SUM(quantity) AS basis_quantity, SUM(total_cost) AS basis_cost, SUM(quantity) FILTER (WHERE valuation_warning IS NOT NULL) AS warning_quantity')->groupBy('inventory_item_id');

        return DB::table('inventory_items as i')->leftJoinSub($stocks, 'stock', 'stock.inventory_item_id', '=', 'i.id')->leftJoinSub($costs, 'cost', 'cost.inventory_item_id', '=', 'i.id')->whereIn('i.id', $ids)->select('i.id', 'i.is_active', 'i.tracks_stock', 'i.kind')->selectRaw('COALESCE(stock.stock_quantity,0)::text AS stock_quantity, COALESCE(cost.basis_quantity,0)::text AS basis_quantity, COALESCE(cost.basis_cost,0)::bigint AS basis_cost, COALESCE(cost.warning_quantity,0)::text AS warning_quantity')->get()->keyBy('id');
    }

    /** @param Collection<int, stdClass> $direct
     * @param Collection<int, stdClass> $packaging
     * @return array<int, string> */
    private function requiredQuantities(Collection $direct, int $directMultiplier, Collection $packaging): array
    {
        $required = [];
        foreach ([$direct, $packaging] as $source => $components) {
            foreach ($components as $component) {
                $id = (int) $component->inventory_item_id;
                $quantity = DecimalMath::multiply((string) $component->base_quantity, (string) ($source === 0 ? $directMultiplier : 1), 6);
                $required[$id] = DecimalMath::subtract($required[$id] ?? '0', '-'.$quantity);
            }
        }

        return $required;
    }

    /** @param Collection<int, stdClass> $components
     * @param Collection<int, stdClass> $current
     * @param array<int, string> $required
     * @param array<int, array{status:string,total_cost:?int,output_base_quantity:?string}> $recipes
     * @return list<array<string,mixed>> */
    private function costLines(Collection $components, int $multiplier, Collection $current, bool $packaging, array $required, array $recipes): array
    {
        $lines = [];
        foreach ($components as $c) {
            $i = $current->get((int) $c->inventory_item_id);
            $quantity = DecimalMath::multiply((string) $c->base_quantity, (string) $multiplier, 6);
            ['coverage' => $coverage, 'warning' => $warning] = $this->valuationOf($i);
            $recipe = $recipes[(int) $c->inventory_item_id] ?? null;
            if ($warning !== null && $recipe !== null && $recipe['status'] === 'complete') {
                // The whole recipe cost spreads over its expected output; never stock-limited.
                $lines[] = ['inventory_item_id' => (int) $c->inventory_item_id, 'item_name' => $c->item_name, 'item_kind' => $i->kind, 'quantity' => DecimalMath::multiply((string) $c->quantity, (string) $multiplier, 6), 'unit_code' => $c->unit_code, 'base_quantity' => $quantity, 'valuation_coverage' => $coverage, 'warning' => null, 'cost_source' => 'recipe',
                    'estimated_cost' => DecimalMath::roundIntegerTimesRatio((int) $recipe['total_cost'], $quantity, (string) $recipe['output_base_quantity']),
                    '_basis' => ['cost' => (string) $recipe['total_cost'], 'quantity' => $quantity, 'basis_quantity' => (string) $recipe['output_base_quantity']]];

                continue;
            }
            if ($warning === null && DecimalMath::compare($required[(int) $c->inventory_item_id], (string) $i->stock_quantity) > 0) {
                $warning = 'insufficient_stock';
            }
            if ($packaging && (! $i->is_active || ! $i->tracks_stock || ! in_array($i->kind, ['packaging', 'consumable'], true))) {
                $warning = 'packaging_item_ineligible';
            }
            $known = $warning === null;
            $lines[] = ['inventory_item_id' => (int) $c->inventory_item_id, 'item_name' => $c->item_name, 'item_kind' => $i->kind, 'quantity' => DecimalMath::multiply((string) $c->quantity, (string) $multiplier, 6), 'unit_code' => $c->unit_code, 'base_quantity' => $quantity, 'valuation_coverage' => $coverage, 'warning' => $warning, 'cost_source' => $known ? 'stock' : null, 'estimated_cost' => $known ? DecimalMath::roundIntegerTimesRatio((int) $i->basis_cost, $quantity, (string) $i->basis_quantity) : null,
                // Retain the exact rational basis only until aggregation, not in UI.
                '_basis' => $known ? ['cost' => (string) $i->basis_cost, 'quantity' => $quantity, 'basis_quantity' => (string) $i->basis_quantity] : null];
        }

        return $lines;
    }

    /** @param list<array<string,mixed>> $lines
     * @return array{status:string,known_components:int,total_components:int,percentage_bps:?int} */
    private function coverage(array $lines, bool $configured): array
    {
        $known = count(array_filter($lines, fn ($l): bool => $l['estimated_cost'] !== null));
        $unreliable = collect($lines)->contains(fn ($l): bool => in_array($l['warning'], ['negative_stock', 'partial_valuation', 'unreliable_valuation', 'insufficient_stock'], true));

        return ['status' => $unreliable ? 'unreliable' : ($configured && $known === count($lines) ? 'complete' : 'incomplete'), 'known_components' => $known, 'total_components' => count($lines), 'percentage_bps' => ! $configured ? null : ($lines === [] ? 10000 : intdiv($known * 10000, count($lines)))];
    }

    /** Round each direct/packaging aggregate once, not each physical unit or component.
     * @param list<array<string,mixed>> $lines */
    private function sumCost(array $lines): int
    {
        $basis = array_values(array_filter(array_column($lines, '_basis')));
        if ($basis === []) {
            return 0;
        }
        $row = DB::selectOne('SELECT round(sum(cost::numeric * quantity::numeric / basis_quantity::numeric))::bigint AS total FROM jsonb_to_recordset(?::jsonb) AS basis(cost text, quantity text, basis_quantity text)', [json_encode($basis, JSON_THROW_ON_ERROR)]);

        return (int) $row->total;
    }

    /** @param list<array<string,mixed>> $lines
     * @return list<array<string,mixed>> */
    private function publicLines(array $lines): array
    {
        return array_map(function ($line): array {
            unset($line['_basis']);

            return $line;
        }, $lines);
    }

    private function quotedPrice(Product $product, int $q, string $mode): ?int
    {
        try {
            $quote = $this->pricing->quote([['product_id' => $product->id, 'quantity' => $q]], OrderCondition::Cooked);
        } catch (ValidationException) {
            return null;
        }

        // A presentation has one quoted line of exactly its units: a dozen at D, or 1 and 6 units at the stored U.
        return count($quote->lines) === 1 && $quote->lines[0]->unitsCount === $q
            && in_array($quote->lines[0]->pricingMode, [$mode, 'unit'], true) ? $quote->lines[0]->lineTotal : null;
    }
}
