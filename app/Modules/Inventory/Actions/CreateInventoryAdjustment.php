<?php

namespace App\Modules\Inventory\Actions;

use App\Modules\Inventory\Data\InventoryAdjustmentData;
use App\Modules\Inventory\Models\InventoryAdjustment;
use App\Modules\Inventory\Models\InventoryItem;
use App\Modules\Inventory\Models\InventoryTransaction;
use App\Modules\Inventory\Models\InventoryUnit;
use App\Modules\Inventory\Queries\InventoryValuationReadModel;
use App\Modules\Inventory\Support\DecimalMath;
use App\Modules\Inventory\Support\MovingWeightedAverageCostAllocator;
use App\Modules\Inventory\Support\ProductionCostProvenance;
use App\Modules\Inventory\Support\UnitConverter;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

final class CreateInventoryAdjustment
{
    public function __construct(private readonly MovingWeightedAverageCostAllocator $allocator, private readonly UnitConverter $converter, private readonly InventoryValuationReadModel $valuation, private readonly ProductionCostProvenance $provenance) {}

    /** @return array<string,mixed> */
    public function preview(InventoryAdjustmentData $data): array
    {
        return DB::transaction(function () use ($data): array {
            return $this->plan($data, $this->lockedItem($data));
        });
    }

    public function execute(InventoryAdjustmentData $data, int $actor): InventoryAdjustment
    {
        $normalized = $this->normalize($data);
        $key = strtolower($data->idempotencyKey);
        $fingerprint = hash_hmac('sha256', json_encode([$data->inventoryItemId, $data->unitId, $data->type, $normalized, $data->effectiveDate->toDateString(), $data->explicitTotalCost, trim($data->note ?? '')], JSON_THROW_ON_ERROR), (string) config('app.key'));

        return DB::transaction(function () use ($data, $actor, $key, $fingerprint): InventoryAdjustment {
            DB::select('SELECT pg_advisory_xact_lock(hashtextextended(?,0))', ['inventory:adjustment:'.$key]);
            $existing = InventoryAdjustment::query()->where('idempotency_key', $key)->first();
            if ($existing !== null) {
                if (! hash_equals($existing->request_fingerprint, $fingerprint)) {
                    throw ValidationException::withMessages(['idempotency_key' => 'This key belongs to a different adjustment payload.']);
                }

                return $existing->load('transaction.movements.costLayer');
            }
            $item = $this->lockedItem($data);
            $p = $this->plan($data, $item);
            if (! $p['can_confirm']) {
                throw ValidationException::withMessages(['quantity' => 'The observed count does not change stock.']);
            }
            $a = InventoryAdjustment::query()->create(['inventory_item_id' => $item->id, 'unit_id' => $data->unitId, 'type' => $data->type, 'quantity' => $p['quantity'], 'delta' => $p['delta'], 'stock_before' => $p['current_stock'], 'physical_count' => $p['physical_count'], 'effective_date' => $data->effectiveDate, 'created_by' => $actor, 'note' => trim($data->note ?? '') ?: null, 'idempotency_key' => $key, 'request_fingerprint' => $fingerprint, 'cost_total' => $p['cost_total'], 'cost_status' => $p['cost_status'], 'cost_coverage' => $p['cost_coverage'], 'cost_basis_quantity' => $p['cost_basis_quantity'], 'cost_basis_total' => $p['cost_basis_total'], 'cost_method' => $p['cost_method'], 'cost_fallback' => $p['cost_fallback'], 'warnings' => $p['warnings']]);
            $t = InventoryTransaction::query()->create(['type' => 'adjustment', 'source_type' => 'adjustment', 'source_id' => 'inventory-adjustment:'.$a->id, 'effective_date' => $data->effectiveDate, 'created_by' => $actor]);
            $m = $t->movements()->create(['inventory_item_id' => $item->id, 'quantity' => $p['delta'], 'unit_id' => $item->base_unit_id]);
            if ($p['cost_total'] !== null) {
                $t->costLayers()->create(['inventory_item_id' => $item->id, 'stock_movement_id' => $m->id, 'quantity' => $p['delta'], 'total_cost' => $p['cost_total'], 'effective_date' => $data->effectiveDate, 'valuation_warning' => $p['cost_fallback']]);
            }
            $a->update(['inventory_transaction_id' => $t->id]);

            return $a->fresh()->load('item.baseUnit', 'unit', 'transaction.movements.costLayer', 'creator', 'reversal');
        });
    }

    private function lockedItem(InventoryAdjustmentData $d): InventoryItem
    {
        $i = InventoryItem::query()->lockForUpdate()->find($d->inventoryItemId);
        if ($i === null || ! $i->is_active || ! $i->tracks_stock) {
            throw ValidationException::withMessages(['inventory_item_id' => 'Choose an active stock-tracked item.']);
        }

        return $i;
    }

    private function normalize(InventoryAdjustmentData $d): string
    {
        if (! Str::isUuid($d->idempotencyKey) || ! in_array($d->type, ['count_correction', 'waste', 'damage', 'expiry', 'manual_adjustment'], true)) {
            throw ValidationException::withMessages(['type' => 'A valid adjustment type and UUID are required.']);
        }
        if (mb_strlen(trim($d->note ?? '')) > 240) {
            throw ValidationException::withMessages(['note' => 'The note may not exceed 240 characters.']);
        }
        if ($d->explicitTotalCost !== null && $d->explicitTotalCost < 0) {
            throw ValidationException::withMessages(['explicit_total_cost' => 'Explicit cost must be non-negative integer pesos.']);
        }
        try {
            return DecimalMath::normalize($d->quantity, 6);
        } catch (InvalidArgumentException $e) {
            throw ValidationException::withMessages(['quantity' => $e->getMessage()]);
        }
    }

    /** @return array<string,mixed> */
    private function plan(InventoryAdjustmentData $d, InventoryItem $item): array
    {
        $q = $this->normalize($d);
        $unit = InventoryUnit::query()->find($d->unitId);
        if ($unit === null || $unit->family !== $item->unit_family) {
            throw ValidationException::withMessages(['unit_id' => 'Use a compatible unit.']);
        }
        $isCount = $d->type === 'count_correction';
        $sign = DecimalMath::compare($q, '0');
        if (($isCount && $sign < 0) || (! $isCount && $sign === 0) || (! in_array($d->type, ['count_correction', 'manual_adjustment'], true) && $sign < 0)) {
            throw ValidationException::withMessages(['quantity' => 'Counts must be non-negative; losses require a positive quantity.']);
        }
        $base = $sign === 0 ? '0.000000' : $this->converter->toBase($item, $unit, ltrim($q, '-'));
        $stock = DecimalMath::normalize((string) $item->stockMovements()->sum('quantity'), 6);
        $delta = $isCount ? DecimalMath::subtract($base, $stock) : (($d->type === 'manual_adjustment' && $sign > 0) ? $base : '-'.$base);
        $delta = DecimalMath::normalize($delta, 6);
        $result = DecimalMath::subtract($stock, DecimalMath::subtract('0', $delta));
        if (strlen(ltrim(explode('.', ltrim($delta, '-'))[0], '0')) > 14 || strlen(ltrim(explode('.', ltrim($result, '-'))[0], '0')) > 14) {
            throw ValidationException::withMessages(['quantity' => 'Resulting inventory quantity exceeds the supported range.']);
        }
        $direction = DecimalMath::compare($delta, '0');
        if ($d->explicitTotalCost !== null && $direction <= 0) {
            throw ValidationException::withMessages(['explicit_total_cost' => 'Explicit cost applies only to a stock increase.']);
        }
        $basis = $this->allocator->valuationsFor([$item->id])[$item->id];
        $classification = $this->valuation->classify(true, $stock, $basis['basis_quantity'], $basis['basis_cost']);
        $warned = isset($this->provenance->unreliableItems([$item->id])[$item->id]);
        if ($classification['coverage'] === 'fully_valued' && $warned) {
            $classification = ['coverage' => 'unreliable', 'value' => null];
        }
        $cost = null;
        $status = 'unknown';
        $bq = null;
        $bc = null;
        $fallback = null;
        $method = null;
        try {
            if ($direction > 0 && $d->explicitTotalCost !== null) {
                $cost = $d->explicitTotalCost;
                $status = 'explicit';
            } elseif ($direction > 0 && $classification['coverage'] === 'fully_valued' && $basis['basis_cost'] >= 0) {
                $cost = DecimalMath::roundIntegerTimesRatio($basis['basis_cost'], $delta, $stock);
                $status = 'estimated';
                $bq = $basis['basis_quantity'];
                $bc = $basis['basis_cost'];
                $method = 'moving_weighted_average';
            } elseif ($direction < 0) {
                [$bq,$bc,$amount,$fallback] = $this->allocator->allocate(ltrim($delta, '-'), $basis);
                $method = 'moving_weighted_average';
                if (DecimalMath::compare($stock, '0') > 0 && DecimalMath::compare($stock, $basis['basis_quantity']) !== 0) {
                    $fallback = 'partial_valuation';
                } elseif ($fallback !== 'no_cost_history') {
                    $cost = -$amount;
                    $status = 'allocated';
                    $fallback ??= $warned ? 'unreliable_input' : (DecimalMath::compare($stock, ltrim($delta, '-')) < 0 ? 'insufficient_stock' : null);
                }
            }
        } catch (InvalidArgumentException $e) {
            throw ValidationException::withMessages(['quantity' => $e->getMessage()]);
        }
        $coverage = $cost === null ? 'unknown' : ($fallback === null ? 'complete' : 'unreliable');
        $warnings = $fallback === null ? ($cost === null && $direction !== 0 ? ['unknown_cost'] : []) : [$fallback];
        if (DecimalMath::compare($result, '0') < 0) {
            $warnings[] = 'negative_stock';
        }
        if ($direction === 0) {
            $warnings = ['no_change'];
        }
        $cq = $cost === null ? $basis['basis_quantity'] : DecimalMath::subtract($basis['basis_quantity'], DecimalMath::subtract('0', $delta));
        if ($cost !== null && (($cost > 0 && $basis['basis_cost'] > PHP_INT_MAX - $cost) || ($cost < 0 && $basis['basis_cost'] < PHP_INT_MIN - $cost))) {
            throw ValidationException::withMessages(['explicit_total_cost' => 'Projected cost exceeds the supported integer range.']);
        }
        $cv = $cost === null ? $basis['basis_cost'] : $basis['basis_cost'] + $cost;
        $resultVal = $this->valuation->classify(true, $result, $cq, $cv);
        if ($resultVal['coverage'] === 'fully_valued' && ($warned || $coverage === 'unreliable')) {
            $resultVal = ['coverage' => 'unreliable', 'value' => null];
        }

        return ['can_confirm' => $direction !== 0, 'quantity' => $q, 'delta' => $delta, 'physical_count' => $isCount ? $base : null, 'current_stock' => $stock, 'result_stock' => $result, 'cost_total' => $cost, 'cost_status' => $status, 'cost_coverage' => $coverage, 'cost_basis_quantity' => $bq, 'cost_basis_total' => $bc, 'cost_method' => $method, 'cost_fallback' => $fallback, 'warnings' => $warnings, 'current_coverage' => $classification['coverage'], 'current_value' => $classification['value'], 'result_coverage' => $resultVal['coverage'], 'result_value' => $resultVal['value']];
    }
}
