<?php

namespace App\Modules\Inventory\Actions;

use App\Modules\Inventory\Models\InventoryItem;
use App\Modules\Inventory\Models\InventoryTransaction;
use App\Modules\Inventory\Models\InventoryUnit;
use App\Modules\Inventory\Models\OrderInventoryConsumption;
use App\Modules\Inventory\Models\ProductInventoryConsumptionDefinition;
use App\Modules\Inventory\Support\DecimalMath;
use App\Modules\Inventory\Support\MovingWeightedAverageCostAllocator;
use App\Modules\Inventory\Support\ProductionCostProvenance;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final readonly class ConsumeDeliveredOrder
{
    public function __construct(private MovingWeightedAverageCostAllocator $allocator, private ProductionCostProvenance $provenance) {}

    public function execute(int $orderId, CarbonImmutable $date, int $actor): OrderInventoryConsumption
    {
        return DB::transaction(function () use ($orderId, $date, $actor): OrderInventoryConsumption {
            $order = DB::table('orders')->where('id', $orderId)->lockForUpdate()->first();
            if ($order === null || $order->status !== 'delivered') {
                throw ValidationException::withMessages(['status' => 'Inventory consumption requires a delivered order.']);
            }
            $existing = OrderInventoryConsumption::query()->where('order_id', $orderId)->first();
            if ($existing !== null) {
                if ($existing->reversal()->exists()) {
                    throw ValidationException::withMessages(['status' => 'A reversed delivery cannot consume again.']);
                }

                return $existing->load('components', 'transaction.movements', 'transaction.costLayers');
            }
            // Commercial units, independent of price family or presentation pricing.
            $lines = DB::table('order_items')->where('order_id', $orderId)->orderBy('id')->get();
            $compositions = DB::table('order_item_compositions')->whereIn('order_item_id', $lines->pluck('id'))->orderBy('id')->get()->groupBy('order_item_id');
            $sold = [];
            foreach ($lines as $line) {
                $parts = $compositions->get($line->id);
                foreach ($parts ?? [$line] as $part) {
                    if ($part->product_id === null) {
                        throw ValidationException::withMessages(['status' => 'An order line has no product configuration identity.']);
                    }
                    $id = (int) $part->product_id;
                    $q = $parts === null ? (int) $line->units_count : (int) $part->quantity;
                    if ($q <= 0) {
                        throw ValidationException::withMessages(['status' => 'Commercial units must be positive.']);
                    }
                    $sold[$id] ??= ['product_id' => $id, 'name' => (string) $part->product_name, 'units' => 0];
                    $sold[$id]['units'] += $q;
                }
            }
            if ($sold === []) {
                throw ValidationException::withMessages(['status' => 'An empty order has no fulfillment definition.']);
            }
            ksort($sold);
            // Lock identity even if no definition exists: configuration uses the same order.
            DB::table('products')->whereIn('id', array_keys($sold))->orderBy('id')->lockForUpdate()->get();
            $definitions = ProductInventoryConsumptionDefinition::query()->whereIn('product_id', array_keys($sold))->orderBy('product_id')->lockForUpdate()->get()->keyBy('product_id');
            $rows = [];
            $snapshots = [];
            foreach ($sold as $id => $s) {
                $d = $definitions->get($id);
                if ($d === null) {
                    throw ValidationException::withMessages(['status' => 'Missing inventory configuration for '.$s['name'].'. Configure consumption or explicitly no inventory.']);
                }
                $v = $d->currentVersion()->lockForUpdate()->first();
                $snapshots[] = [...$s, 'mode' => $d->mode, 'version_id' => $v?->id];
                if ($d->mode === 'no_inventory') {
                    continue;
                }
                if ($v === null || ! $v->is_active || ! $v->is_sealed || ! $v->components()->exists()) {
                    throw ValidationException::withMessages(['status' => 'Inventory configuration is incomplete for '.$s['name'].'.']);
                }
                foreach ($v->components as $component) {
                    $rows[] = ['product_id' => $id, 'product_name_snapshot' => $s['name'], 'consumption_version_id' => $v->id, 'definition_component_id' => $component->id, 'inventory_item_id' => $component->inventory_item_id, 'quantity' => $component->quantity, 'unit_id' => $component->unit_id, 'component_base_quantity' => $component->base_quantity, 'commercial_quantity' => $s['units'], 'consumed_base_quantity' => DecimalMath::multiply($component->base_quantity, (string) $s['units'], 6)];
                }
            }
            $ids = array_values(array_unique(array_column($rows, 'inventory_item_id')));
            sort($ids);
            $items = InventoryItem::query()->whereKey($ids)->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            foreach ($items as $i) {
                if (! $i->is_active || ! $i->tracks_stock || ! $i->order_consumption_enabled) {
                    throw ValidationException::withMessages(['status' => 'All consumption components must remain sale eligible.']);
                }
            }
            $c = OrderInventoryConsumption::query()->create(['order_id' => $orderId, 'effective_date' => $date, 'created_by' => $actor, 'products_snapshot' => $snapshots, 'total_cost' => 0, 'cost_coverage' => 'complete', 'warnings' => []]);
            if ($rows === []) {
                return $c->load('components', 'transaction');
            }
            $tx = InventoryTransaction::query()->create(['type' => 'sale_consumption', 'source_type' => 'order', 'source_id' => (string) $orderId, 'effective_date' => $date, 'created_by' => $actor]);
            $valuations = $this->allocator->valuationsFor($ids);
            $unreliable = $this->provenance->unreliableItems($ids);
            $units = InventoryUnit::query()->get()->keyBy('id');
            $warnings = [];
            $total = 0;
            $known = 0;
            foreach ($ids as $id) {
                $group = array_values(array_filter($rows, static fn (array $r): bool => $r['inventory_item_id'] === $id));
                $quantities = array_column($group, 'consumed_base_quantity');
                $q = '0.000000';
                foreach ($quantities as $part) {
                    $q = DecimalMath::subtract($q, '-'.$part);
                }
                $i = $items->get($id);
                $basis = $valuations[$id];
                [$bq,$bc,$cost,$fallback] = $this->allocator->allocate($q, $basis);
                $stock = (string) $i->stockMovements()->sum('quantity');
                if (DecimalMath::compare($stock, '0') > 0 && DecimalMath::compare($stock, $basis['basis_quantity']) !== 0) {
                    $cost = null;
                    $fallback = 'partial_valuation';
                } elseif ($fallback === 'no_cost_history') {
                    $cost = null;
                } elseif (isset($unreliable[$id])) {
                    $fallback ??= 'unreliable_input';
                }
                if ($cost !== null && $fallback === null && DecimalMath::compare($stock, $q) < 0) {
                    $fallback = 'insufficient_stock';
                }
                $m = $tx->movements()->create(['inventory_item_id' => $id, 'quantity' => '-'.$q, 'unit_id' => $i->base_unit_id]);
                $layer = $cost === null ? null : $tx->costLayers()->create(['inventory_item_id' => $id, 'stock_movement_id' => $m->id, 'quantity' => '-'.$q, 'total_cost' => -$cost, 'effective_date' => $date, 'valuation_warning' => $fallback]);
                $shares = $cost === null ? array_fill(0, count($group), null) : DecimalMath::allocateIntegerByRatios($cost, $quantities);
                foreach ($group as $index => $r) {
                    $c->components()->create([...$r, 'inventory_item_name_snapshot' => $i->name, 'unit_code_snapshot' => $units->get($r['unit_id'])->code, 'base_unit_code_snapshot' => $units->get($i->base_unit_id)->code, 'stock_movement_id' => $m->id, 'inventory_cost_layer_id' => $layer?->id, 'basis_quantity' => $bq, 'basis_total_cost' => $bc, 'allocated_cost' => $shares[$index], 'method' => 'moving_weighted_average', 'fallback' => $fallback]);
                }
                if ($cost !== null) {
                    $total += $cost;
                    $known++;
                }
                if ($fallback !== null) {
                    $warnings[] = ['inventory_item_id' => $id, 'code' => $fallback];
                }
            }
            $c->update(['inventory_transaction_id' => $tx->id, 'total_cost' => $known === count($ids) ? $total : null, 'cost_coverage' => $known === count($ids) ? ($warnings === [] ? 'complete' : 'unreliable') : ($known === 0 ? 'unknown' : 'partial'), 'warnings' => $warnings]);

            return $c->fresh()->load('components', 'transaction.movements', 'transaction.costLayers');
        });
    }
}
