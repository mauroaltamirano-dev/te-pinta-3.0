<?php

namespace App\Modules\Inventory\Actions;

use App\Modules\Inventory\Models\InventoryAdjustment;
use App\Modules\Inventory\Models\InventoryItem;
use App\Modules\Inventory\Models\InventoryTransaction;
use App\Modules\Inventory\Support\DecimalMath;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class ReverseInventoryAdjustment
{
    public function execute(int $id, CarbonImmutable $date, string $note, int $actor): InventoryAdjustment
    {
        if (trim($note) === '' || mb_strlen($note) > 240) {
            throw ValidationException::withMessages(['note' => 'Ingresá un motivo de hasta 240 caracteres.']);
        }

        return DB::transaction(function () use ($id, $date, $note, $actor): InventoryAdjustment {
            $itemId = InventoryAdjustment::query()->whereKey($id)->value('inventory_item_id');
            InventoryItem::query()->lockForUpdate()->findOrFail($itemId);
            $original = InventoryAdjustment::query()->with('transaction.movements.costLayer')->lockForUpdate()->findOrFail($id);
            if ($original->reversal_of_id !== null || $original->reversal()->exists()) {
                throw ValidationException::withMessages(['adjustment' => 'Este ajuste ya fue revertido o es una reversión. Recargá la página para ver su estado.']);
            }
            $delta = DecimalMath::subtract('0', $original->delta);
            $stock = DecimalMath::normalize((string) DB::table('inventory_stock_movements')->where('inventory_item_id', $itemId)->sum('quantity'), 6);
            $r = InventoryAdjustment::query()->create(['inventory_item_id' => $itemId, 'unit_id' => $original->unit_id, 'type' => 'reversal', 'quantity' => DecimalMath::subtract('0', $original->quantity), 'delta' => $delta, 'stock_before' => $stock, 'physical_count' => null, 'effective_date' => $date, 'created_by' => $actor, 'note' => trim($note), 'reversal_of_id' => $id, 'idempotency_key' => (string) Str::uuid(), 'request_fingerprint' => hash('sha256', 'reverse:'.$id), 'cost_total' => $original->cost_total === null ? null : -$original->cost_total, 'cost_status' => $original->cost_total === null ? 'unknown' : 'reversal', 'cost_coverage' => $original->cost_coverage, 'cost_basis_quantity' => $original->cost_basis_quantity, 'cost_basis_total' => $original->cost_basis_total, 'cost_method' => $original->cost_method, 'cost_fallback' => $original->cost_fallback, 'warnings' => $original->warnings]);
            $t = InventoryTransaction::query()->create(['type' => 'reversal', 'source_type' => 'reversal', 'source_id' => 'inventory-adjustment-reversal:'.$id, 'reversal_of_id' => $original->inventory_transaction_id, 'effective_date' => $date, 'created_by' => $actor]);
            $m = $t->movements()->create(['inventory_item_id' => $itemId, 'unit_id' => $original->transaction->movements->sole()->unit_id, 'quantity' => $delta]);
            $layer = $original->transaction->movements->sole()->costLayer;
            if ($layer !== null) {
                $t->costLayers()->create(['inventory_item_id' => $itemId, 'stock_movement_id' => $m->id, 'quantity' => $delta, 'total_cost' => -$layer->total_cost, 'effective_date' => $date, 'reversal_of_id' => $layer->id, 'valuation_warning' => $layer->valuation_warning]);
            }
            $r->update(['inventory_transaction_id' => $t->id]);

            return $r->fresh()->load('item.baseUnit', 'unit', 'transaction.movements.costLayer', 'creator');
        });
    }
}
