<?php

namespace App\Modules\Inventory\Actions;

use App\Modules\Inventory\Models\InventoryItem;
use App\Modules\Inventory\Models\InventoryTransaction;
use App\Modules\Inventory\Models\OrderInventoryConsumption;
use App\Modules\Inventory\Models\OrderInventoryConsumptionReversal;
use App\Modules\Inventory\Support\DecimalMath;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

final class ReverseOrderInventoryConsumption
{
    public function execute(int $orderId, CarbonImmutable $date, int $actor): ?OrderInventoryConsumptionReversal
    {
        return DB::transaction(function () use ($orderId, $date, $actor): ?OrderInventoryConsumptionReversal {
            DB::table('orders')->where('id', $orderId)->lockForUpdate()->first();
            $c = OrderInventoryConsumption::query()->where('order_id', $orderId)->lockForUpdate()->first();
            if ($c === null) {
                return null;
            }
            $existing = $c->reversal()->first();
            if ($existing !== null) {
                return $existing->load('transaction.movements.costLayer');
            }
            $source = $c->transaction()->with('movements.costLayer')->first();
            if ($source !== null) {
                InventoryItem::query()->whereKey($source->movements->pluck('inventory_item_id'))->orderBy('id')->lockForUpdate()->get();
            }
            $r = OrderInventoryConsumptionReversal::query()->create(['order_inventory_consumption_id' => $c->id, 'effective_date' => $date, 'created_by' => $actor]);
            if ($source === null) {
                return $r->load('transaction');
            }
            $tx = InventoryTransaction::query()->create(['type' => 'reversal', 'source_type' => 'reversal', 'source_id' => 'order-consumption-reversal:'.$c->id, 'reversal_of_id' => $source->id, 'effective_date' => $date, 'created_by' => $actor]);
            foreach ($source->movements as $m) {
                $opposite = $tx->movements()->create(['inventory_item_id' => $m->inventory_item_id, 'quantity' => DecimalMath::subtract('0', $m->quantity), 'unit_id' => $m->unit_id]);
                $l = $m->costLayer;
                if ($l !== null) {
                    $tx->costLayers()->create(['inventory_item_id' => $m->inventory_item_id, 'stock_movement_id' => $opposite->id, 'quantity' => $opposite->quantity, 'total_cost' => -$l->total_cost, 'effective_date' => $date, 'reversal_of_id' => $l->id, 'valuation_warning' => $l->valuation_warning]);
                }
            }
            $r->update(['inventory_transaction_id' => $tx->id]);

            return $r->fresh()->load('transaction.movements.costLayer');
        });
    }
}
