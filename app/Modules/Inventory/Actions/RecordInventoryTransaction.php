<?php

namespace App\Modules\Inventory\Actions;

use App\Modules\Inventory\Data\InventoryTransactionData;
use App\Modules\Inventory\Enums\InventoryTransactionType;
use App\Modules\Inventory\Models\InventoryItem;
use App\Modules\Inventory\Models\InventoryTransaction;
use App\Modules\Inventory\Support\DecimalMath;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

final class RecordInventoryTransaction
{
    public function execute(InventoryTransactionData $data, int $adminId): InventoryTransaction
    {
        if ($data->movements === []) {
            throw ValidationException::withMessages(['movements' => 'El movimiento de inventario no tiene líneas. Agregá al menos un insumo.']);
        }
        if (($data->sourceType === null) !== ($data->sourceId === null)) {
            throw ValidationException::withMessages(['source' => 'El origen del movimiento está incompleto. Recargá la página y volvé a intentarlo.']);
        }
        if (($data->type === InventoryTransactionType::Reversal) !== ($data->reversalOfId !== null)) {
            throw ValidationException::withMessages(['reversal_of_id' => 'Solo una reversión puede referirse a otro movimiento. Recargá la página y volvé a intentarlo.']);
        }
        $expectedSource = match ($data->type) {
            InventoryTransactionType::PurchaseReceipt => 'purchase',
            InventoryTransactionType::Adjustment => 'adjustment',
            InventoryTransactionType::Production, InventoryTransactionType::ProductionConsumption => 'production',
            InventoryTransactionType::SaleConsumption => 'order',
            InventoryTransactionType::Reversal => 'reversal',
        };
        if ($data->sourceType?->value !== $expectedSource) {
            throw ValidationException::withMessages(['source_type' => 'El origen no corresponde a este tipo de movimiento. Recargá la página y volvé a intentarlo.']);
        }

        return DB::transaction(function () use ($data, $adminId): InventoryTransaction {
            $itemIds = collect($data->movements)->pluck('inventoryItemId')->unique()->sort()->values();
            $items = InventoryItem::query()->whereKey($itemIds)->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            if ($items->count() !== $itemIds->count()) {
                throw ValidationException::withMessages(['movements' => 'Algún insumo ya no existe. Recargá la página y elegí insumos vigentes.']);
            }

            $transaction = InventoryTransaction::query()->create([
                'type' => $data->type,
                'effective_date' => $data->effectiveDate,
                'source_type' => $data->sourceType,
                'source_id' => $data->sourceId,
                'description' => $data->description,
                'created_by' => $adminId,
                'reversal_of_id' => $data->reversalOfId,
            ]);

            foreach ($data->movements as $index => $movementData) {
                $item = $items->get($movementData->inventoryItemId);
                if (! $item->tracks_stock) {
                    throw ValidationException::withMessages(["movements.{$index}.inventory_item_id" => 'Este insumo no controla stock. Elegí un insumo que controle stock.']);
                }
                try {
                    $quantity = DecimalMath::normalize($movementData->quantity, 6);
                } catch (InvalidArgumentException $exception) {
                    throw ValidationException::withMessages(["movements.{$index}.quantity" => $exception->getMessage()]);
                }
                if (trim(str_replace(['-', '.'], '', $quantity), '0') === '') {
                    throw ValidationException::withMessages(["movements.{$index}.quantity" => 'La cantidad no puede ser cero. Ingresá una cantidad distinta de cero.']);
                }
                $fraction = explode('.', ltrim($quantity, '-'), 2)[1] ?? '';
                if (trim(substr($fraction, $item->quantity_precision), '0') !== '') {
                    throw ValidationException::withMessages(["movements.{$index}.quantity" => 'La cantidad tiene más decimales de los que admite el insumo. Redondeala.']);
                }

                $movement = $transaction->movements()->create([
                    'inventory_item_id' => $item->id,
                    'quantity' => $quantity,
                    'unit_id' => $item->base_unit_id,
                ]);

                if ($movementData->totalCost !== null) {
                    if ($movementData->reversalOfCostLayerId === null && ($movementData->totalCost < 0 || $quantity[0] === '-')) {
                        throw ValidationException::withMessages(["movements.{$index}.total_cost" => 'El costo solo se registra en entradas de stock y no puede ser negativo. Revisá la cantidad y el costo.']);
                    }
                    $transaction->costLayers()->create([
                        'inventory_item_id' => $item->id,
                        'stock_movement_id' => $movement->id,
                        'quantity' => $quantity,
                        'total_cost' => $movementData->totalCost,
                        'reversal_of_id' => $movementData->reversalOfCostLayerId,
                        'effective_date' => $data->effectiveDate,
                    ]);
                }
            }

            return $transaction->load('movements.item.baseUnit', 'movements.costLayer', 'costLayers');
        });
    }
}
