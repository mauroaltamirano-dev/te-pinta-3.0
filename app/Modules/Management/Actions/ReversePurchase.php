<?php

namespace App\Modules\Management\Actions;

use App\Modules\Inventory\Actions\RecordInventoryTransaction;
use App\Modules\Inventory\Data\InventoryMovementData;
use App\Modules\Inventory\Data\InventoryTransactionData;
use App\Modules\Inventory\Enums\InventorySourceType;
use App\Modules\Inventory\Enums\InventoryTransactionType;
use App\Modules\Inventory\Models\InventoryItem;
use App\Modules\Management\Data\ReversalData;
use App\Modules\Management\Enums\FinancialTransactionType;
use App\Modules\Management\Models\FinancialTransaction;
use App\Modules\Management\Models\Purchase;
use App\Modules\Management\Models\PurchaseReversal;
use App\Modules\Management\Models\Wallet;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

final class ReversePurchase
{
    public function __construct(private readonly RecordInventoryTransaction $recordInventoryTransaction) {}

    public function execute(Purchase $purchase, ReversalData $data, int $adminId): PurchaseReversal
    {
        if (trim((string) $data->description) === '') {
            throw ValidationException::withMessages(['description' => 'A reversal reason is required.']);
        }
        $reversal = DB::transaction(function () use ($purchase, $data, $adminId): PurchaseReversal {
            $locked = Purchase::query()->with(['financialTransaction.entries', 'inventoryTransaction.movements.costLayer'])
                ->lockForUpdate()->findOrFail($purchase->id);
            if (PurchaseReversal::query()->where('purchase_id', $locked->id)->exists()) {
                throw ValidationException::withMessages(['purchase' => 'The purchase has already been reversed.']);
            }

            Wallet::query()->whereKey($locked->wallet_id)->lockForUpdate()->firstOrFail();
            $itemIds = $locked->inventoryTransaction?->movements->pluck('inventory_item_id')->sort()->values() ?? collect();
            if ($itemIds->isNotEmpty()) {
                InventoryItem::query()->whereKey($itemIds)->orderBy('id')->lockForUpdate()->get();
            }
            $originalFinancial = FinancialTransaction::query()->lockForUpdate()->findOrFail($locked->financial_transaction_id);
            $financialReversal = FinancialTransaction::query()->create([
                'type' => FinancialTransactionType::Reversal,
                'description' => $data->description,
                'effective_date' => $data->effectiveDate,
                'created_by' => $adminId,
                'reversal_of_id' => $originalFinancial->id,
            ]);
            foreach ($locked->financialTransaction->entries as $entry) {
                $financialReversal->entries()->create(['wallet_id' => $entry->wallet_id, 'amount' => -((int) $entry->amount)]);
            }

            $inventoryReversal = null;
            if ($locked->inventoryTransaction !== null) {
                $inventoryReversal = $this->recordInventoryTransaction->execute(new InventoryTransactionData(
                    type: InventoryTransactionType::Reversal,
                    effectiveDate: $data->effectiveDate,
                    movements: array_values($locked->inventoryTransaction->movements->map(function ($movement): InventoryMovementData {
                        $layer = $movement->costLayer;

                        return new InventoryMovementData(
                            $movement->inventory_item_id,
                            '-'.$movement->quantity,
                            $layer === null ? null : -$layer->total_cost,
                            $layer?->id,
                        );
                    })->all()),
                    sourceType: InventorySourceType::Reversal,
                    sourceId: 'purchase-reversal:'.$locked->id,
                    description: $data->description,
                    reversalOfId: $locked->inventory_transaction_id,
                ), $adminId);
            }

            return PurchaseReversal::query()->create([
                'purchase_id' => $locked->id,
                'financial_transaction_id' => $financialReversal->id,
                'inventory_transaction_id' => $inventoryReversal?->id,
                'description' => $data->description,
                'effective_date' => $data->effectiveDate,
                'created_by' => $adminId,
            ]);
        });

        Log::info('[Management] purchase.reversed', ['module' => 'Management', 'purchase_id' => $purchase->id,
            'purchase_reversal_id' => $reversal->id, 'financial_transaction_id' => $reversal->financial_transaction_id,
            'inventory_transaction_id' => $reversal->inventory_transaction_id, 'admin_id' => $adminId]);

        return $reversal->load(['financialTransaction.entries.wallet', 'inventoryTransaction.movements.costLayer']);
    }
}
