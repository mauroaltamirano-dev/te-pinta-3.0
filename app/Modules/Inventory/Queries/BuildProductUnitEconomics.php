<?php

namespace App\Modules\Inventory\Queries;

use App\Modules\Inventory\Models\CostingPolicy;
use App\Modules\Inventory\Models\ProductInventoryConsumptionDefinition;
use App\Modules\Inventory\Support\DecimalMath;
use App\Modules\Orders\Enums\OrderCondition;
use App\Modules\Orders\Pricing\OrderPricingEngine;
use App\Modules\Products\Enums\ProductPricingFamily;
use App\Modules\Products\Models\Product;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use stdClass;

/** Read-only current estimates. No recipe expansion, stock allocation or historical writes. */
final readonly class BuildProductUnitEconomics
{
    public function __construct(private InventoryValuationReadModel $valuation, private OrderPricingEngine $pricing) {}

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
            throw ValidationException::withMessages(['quantity' => 'Choose 1–120 units for a unit-priced product; empanadas use their standard presentations.']);
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
        $modes = $product->pricing_family === ProductPricingFamily::Empanada ? [1 => 'unit', 6 => 'half_dozen', 12 => 'dozen'] : [$unitQuantity => 'unit'];
        $presentations = [];
        foreach ($modes as $q => $mode) {
            $rule = $policyStatus === 'active' ? $policy->presentations->firstWhere('commercial_quantity', $q) : null;
            $packagingStatus = $rule->packaging_mode ?? 'missing';
            $presentationPackaging = $packaging->where('commercial_quantity', $q);
            $required = $this->requiredQuantities($direct, $q, $presentationPackaging);
            $dl = $this->costLines($direct, $q, $current, false, $required);
            $pl = $this->costLines($presentationPackaging, 1, $current, true, $required);
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
                'warnings' => $warnings, 'direct_components' => $this->publicLines($dl), 'packaging_components' => $this->publicLines($pl),
            ];
        }

        return ['product_id' => $product->id, 'product_name' => $product->name, 'pricing_family' => $product->pricing_family->value, 'condition' => 'cooked', 'physical_status' => $physical, 'policy_status' => $policyStatus, 'costing_policy' => $policy === null ? null : ['id' => $policy->id, 'name' => $policy->name, 'overhead_basis_points' => $policy->overhead_basis_points], 'presentations' => $presentations];
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
     * @return list<array<string,mixed>> */
    private function costLines(Collection $components, int $multiplier, Collection $current, bool $packaging, array $required): array
    {
        $lines = [];
        foreach ($components as $c) {
            $i = $current->get((int) $c->inventory_item_id);
            $quantity = DecimalMath::multiply((string) $c->base_quantity, (string) $multiplier, 6);
            $coverage = $this->valuation->classify((bool) $i->tracks_stock, (string) $i->stock_quantity, (string) $i->basis_quantity, (int) $i->basis_cost)['coverage'];
            if ($coverage === 'fully_valued' && DecimalMath::compare((string) $i->warning_quantity, '0') > 0) {
                $coverage = 'unreliable';
            }
            $warning = match ($coverage) {
                'fully_valued' => null,'negative' => 'negative_stock','partial' => 'partial_valuation','unreliable' => 'unreliable_valuation',default => 'no_current_valuation'
            };
            if ($warning === null && DecimalMath::compare($required[(int) $c->inventory_item_id], (string) $i->stock_quantity) > 0) {
                $warning = 'insufficient_stock';
            }
            if ($packaging && (! $i->is_active || ! $i->tracks_stock || ! in_array($i->kind, ['packaging', 'consumable'], true))) {
                $warning = 'packaging_item_ineligible';
            }
            $known = $warning === null;
            $lines[] = ['inventory_item_id' => (int) $c->inventory_item_id, 'item_name' => $c->item_name, 'quantity' => DecimalMath::multiply((string) $c->quantity, (string) $multiplier, 6), 'unit_code' => $c->unit_code, 'base_quantity' => $quantity, 'valuation_coverage' => $coverage, 'warning' => $warning, 'estimated_cost' => $known ? DecimalMath::roundIntegerTimesRatio((int) $i->basis_cost, $quantity, (string) $i->basis_quantity) : null,
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
