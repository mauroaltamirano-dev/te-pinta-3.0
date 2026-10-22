<?php

namespace App\Modules\Inventory\Actions;

use App\Modules\Inventory\Enums\InventorySourceType;
use App\Modules\Inventory\Enums\InventoryTransactionType;
use App\Modules\Inventory\Models\InventoryItem;
use App\Modules\Inventory\Models\InventoryTransaction;
use App\Modules\Inventory\Models\ProductionBatch;
use App\Modules\Inventory\Models\ProductionCostAllocation;
use App\Modules\Inventory\Models\ProductionReversal;
use App\Modules\Inventory\Support\DecimalMath;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class ReverseProductionBatch
{
    public function execute(int $batchId, CarbonImmutable $date, string $reason, int $adminId): ProductionReversal
    {
        if (trim($reason) === '' || mb_strlen($reason) > 240) {
            throw ValidationException::withMessages(['reason' => 'Ingresá un motivo de hasta 240 caracteres.']);
        }

        return DB::transaction(function () use ($batchId, $date, $reason, $adminId): ProductionReversal {
            $b = ProductionBatch::query()->with('transaction.movements.costLayer', 'allocations')->lockForUpdate()->findOrFail($batchId);
            if ($b->reversal()->exists()) {
                throw ValidationException::withMessages(['production' => 'Esta producción ya fue revertida. Recargá la página para ver su estado.']);
            }
            $original = $b->transaction;
            $ids = $original->movements->pluck('inventory_item_id')->unique()->sort()->values();
            InventoryItem::query()->whereKey($ids)->orderBy('id')->lockForUpdate()->get();
            $tx = InventoryTransaction::query()->create(['type' => InventoryTransactionType::Reversal, 'effective_date' => $date, 'source_type' => InventorySourceType::Reversal, 'source_id' => (string) $original->id, 'created_by' => $adminId, 'reversal_of_id' => $original->id]);
            // Source is created before child facts so append guards bind to this transaction's full XID.
            $r = ProductionReversal::query()->create(['production_batch_id' => $b->id, 'inventory_transaction_id' => $tx->id, 'effective_date' => $date, 'reason' => trim($reason), 'created_by' => $adminId]);
            foreach ($original->movements as $m) {
                $opposite = $tx->movements()->create(['inventory_item_id' => $m->inventory_item_id, 'quantity' => DecimalMath::subtract('0', $m->quantity), 'unit_id' => $m->unit_id]);
                $l = $m->costLayer;
                $new = $l === null ? null : $tx->costLayers()->create(['inventory_item_id' => $l->inventory_item_id, 'stock_movement_id' => $opposite->id, 'quantity' => DecimalMath::subtract('0', $l->quantity), 'total_cost' => -$l->total_cost, 'effective_date' => $date, 'reversal_of_id' => $l->id, 'valuation_warning' => $l->valuation_warning]);
                $a = $b->allocations->firstWhere('stock_movement_id', $m->id);
                if ($a !== null) {
                    ProductionCostAllocation::query()->create(['production_batch_id' => $b->id, 'stock_movement_id' => $opposite->id, 'inventory_cost_layer_id' => $new?->id, 'basis_quantity' => DecimalMath::subtract('0', $a->basis_quantity), 'basis_total_cost' => -$a->basis_total_cost, 'allocated_quantity' => DecimalMath::subtract('0', $a->allocated_quantity), 'allocated_cost' => $a->allocated_cost === null ? null : -$a->allocated_cost, 'method' => $a->method, 'fallback' => $a->fallback, 'reversal_of_id' => $a->id]);
                }
            }

            return $r->load('inventoryTransaction.movements.costLayer', 'batch');
        });
    }
}
