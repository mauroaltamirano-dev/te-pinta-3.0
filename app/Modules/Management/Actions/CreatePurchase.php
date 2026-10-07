<?php

namespace App\Modules\Management\Actions;

use App\Modules\Inventory\Actions\RecordInventoryTransaction;
use App\Modules\Inventory\Data\InventoryMovementData;
use App\Modules\Inventory\Data\InventoryTransactionData;
use App\Modules\Inventory\Enums\InventorySourceType;
use App\Modules\Inventory\Enums\InventoryTransactionType;
use App\Modules\Inventory\Models\InventoryItem;
use App\Modules\Inventory\Models\InventoryUnit;
use App\Modules\Inventory\Models\PurchasePresentation;
use App\Modules\Inventory\Support\DecimalMath;
use App\Modules\Inventory\Support\UnitConverter;
use App\Modules\Management\Data\PurchaseData;
use App\Modules\Management\Enums\FinancialTransactionType;
use App\Modules\Management\Enums\PurchasePriceMode;
use App\Modules\Management\Models\FinancialTransaction;
use App\Modules\Management\Models\Purchase;
use App\Modules\Management\Models\Wallet;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use Throwable;

final class CreatePurchase
{
    public function __construct(
        private readonly UnitConverter $unitConverter,
        private readonly RecordInventoryTransaction $recordInventoryTransaction,
    ) {}

    public function execute(PurchaseData $data, int $adminId): Purchase
    {
        if (! Str::isUuid($data->idempotencyKey) || $data->lines === [] || count($data->lines) > 100 || $data->shippingAmount < 0 || $data->shippingAmount > 999999999999) {
            throw ValidationException::withMessages(['purchase' => 'Invalid purchase identity, lines or shipping amount.']);
        }
        foreach ($data->lines as $line) {
            if ($line->priceAmount < 0 || $line->priceAmount > 999999999999) {
                throw ValidationException::withMessages(['lines' => 'Line price must be non-negative integer pesos within the supported range.']);
            }
        }
        // PostgreSQL UUID identity ignores case; locks and physical source text must share it.
        $idempotencyKey = strtolower($data->idempotencyKey);
        $fingerprint = $this->fingerprint($data);

        try {
            $purchase = DB::transaction(function () use ($data, $adminId, $idempotencyKey, $fingerprint): Purchase {
                DB::select('SELECT pg_advisory_xact_lock(hashtextextended(?, 0))', [$idempotencyKey]);
                $existing = Purchase::query()->where('idempotency_key', $idempotencyKey)->first();
                if ($existing !== null) {
                    if (! hash_equals($existing->request_fingerprint, $fingerprint)) {
                        throw ValidationException::withMessages(['idempotency_key' => 'La clave de idempotencia ya fue utilizada con otra compra.']);
                    }

                    return $this->load($existing);
                }

                $wallet = Wallet::query()->lockForUpdate()->findOrFail($data->walletId);
                if ($wallet->archived_at !== null) {
                    throw ValidationException::withMessages(['wallet_id' => 'La billetera archivada no puede recibir compras nuevas.']);
                }

                $itemIds = collect($data->lines)->pluck('inventoryItemId')->unique()->sort()->values();
                if ($itemIds->count() !== count($data->lines)) {
                    throw ValidationException::withMessages(['lines' => 'Un item de Inventory puede aparecer una sola vez en la compra.']);
                }
                $presentationIds = collect($data->lines)->pluck('purchasePresentationId')->filter()->unique()->sort()->values();
                $presentations = PurchasePresentation::query()->whereKey($presentationIds)->orderBy('id')->lockForUpdate()->get()
                    ->load('contentUnit')->keyBy('id');
                if ($presentations->count() !== $presentationIds->count()) {
                    throw ValidationException::withMessages(['lines' => 'Todas las presentaciones deben existir.']);
                }
                $items = InventoryItem::query()->whereKey($itemIds)->orderBy('id')->lockForUpdate()->get()
                    ->load(['category', 'baseUnit'])->keyBy('id');
                if ($items->count() !== $itemIds->count()) {
                    throw ValidationException::withMessages(['lines' => 'Todos los items de Inventory deben existir.']);
                }
                $unitIds = collect($data->lines)->pluck('inputUnitId')->filter()->unique()->values();
                $units = InventoryUnit::query()->whereKey($unitIds)->get()->keyBy('id');
                if ($units->count() !== $unitIds->count()) {
                    throw ValidationException::withMessages(['lines' => 'Todas las unidades de entrada deben existir.']);
                }

                $normalized = [];
                foreach ($data->lines as $index => $line) {
                    $item = $items->get($line->inventoryItemId);
                    if (! $item->is_active) {
                        throw ValidationException::withMessages(["lines.{$index}.inventory_item_id" => 'El item de Inventory está inactivo.']);
                    }
                    try {
                        $quantity = DecimalMath::normalize($line->quantity, 6);
                    } catch (InvalidArgumentException $exception) {
                        throw ValidationException::withMessages(["lines.{$index}.quantity" => $exception->getMessage()]);
                    }
                    if ($quantity[0] === '-' || trim(str_replace('.', '', $quantity), '0') === '') {
                        throw ValidationException::withMessages(["lines.{$index}.quantity" => 'La cantidad debe ser positiva.']);
                    }

                    $presentation = null;
                    $inputUnit = null;
                    if (($line->purchasePresentationId === null) === ($line->inputUnitId === null)) {
                        throw ValidationException::withMessages(["lines.{$index}" => 'Elegí una presentación o una unidad, no ambas.']);
                    }
                    if ($line->purchasePresentationId !== null) {
                        $presentation = $presentations->get($line->purchasePresentationId);
                        if ($presentation->inventory_item_id !== $item->id || ! $presentation->is_active) {
                            throw ValidationException::withMessages(["lines.{$index}.purchase_presentation_id" => 'La presentación no está activa para este item.']);
                        }
                        $contentBase = $this->unitConverter->toBase($item, $presentation->contentUnit, $presentation->content_quantity);
                        try {
                            $baseQuantity = DecimalMath::multiply($quantity, $contentBase, 6);
                        } catch (InvalidArgumentException $exception) {
                            throw ValidationException::withMessages(["lines.{$index}.quantity" => $exception->getMessage()]);
                        }
                    } else {
                        $inputUnit = $units->get($line->inputUnitId);
                        $baseQuantity = $this->unitConverter->toBase($item, $inputUnit, $quantity);
                    }

                    $lineTotal = $line->priceAmount;
                    if ($line->priceMode === PurchasePriceMode::Unit) {
                        try {
                            $exactTotal = DecimalMath::multiply((string) $line->priceAmount, $quantity, 0);
                            if (DecimalMath::compare($exactTotal, '999999999999') > 0) {
                                throw new InvalidArgumentException('Line total exceeds supported money range.');
                            }
                            $lineTotal = (int) $exactTotal;
                        } catch (InvalidArgumentException) {
                            throw ValidationException::withMessages(["lines.{$index}.price_amount" => 'El precio unitario debe producir un total de línea en pesos enteros.']);
                        }
                    }
                    if (DecimalMath::compare($baseQuantity, '99999999999999.999999') > 0 || trim(substr(explode('.', $baseQuantity)[1] ?? '', $item->quantity_precision), '0') !== '') {
                        throw ValidationException::withMessages(["lines.{$index}.quantity" => 'Normalized quantity exceeds the item range or precision.']);
                    }
                    $normalized[] = compact('line', 'item', 'presentation', 'inputUnit', 'quantity', 'baseQuantity', 'lineTotal');
                }

                $subtotal = array_sum(array_column($normalized, 'lineTotal'));
                if ($subtotal + $data->shippingAmount <= 0 || $subtotal + $data->shippingAmount > 999999999999) {
                    throw ValidationException::withMessages(['lines' => 'El total de la compra debe ser positivo.']);
                }

                $financial = FinancialTransaction::query()->create([
                    'type' => FinancialTransactionType::Purchase,
                    'description' => $data->description ?? $data->supplierName,
                    'effective_date' => $data->effectiveDate,
                    'created_by' => $adminId,
                ]);
                $financial->entries()->create(['wallet_id' => $wallet->id, 'amount' => -($subtotal + $data->shippingAmount)]);

                $stockable = array_values(array_filter($normalized, fn (array $entry): bool => $entry['item']->tracks_stock));
                $inventory = $stockable === [] ? null : $this->recordInventoryTransaction->execute(new InventoryTransactionData(
                    type: InventoryTransactionType::PurchaseReceipt,
                    effectiveDate: $data->effectiveDate,
                    movements: array_map(fn (array $entry): InventoryMovementData => new InventoryMovementData(
                        $entry['item']->id, $entry['baseQuantity'], $entry['lineTotal'],
                    ), $stockable),
                    sourceType: InventorySourceType::Purchase,
                    sourceId: $idempotencyKey,
                    description: $data->description ?? $data->supplierName,
                ), $adminId);

                $purchase = Purchase::query()->create([
                    'idempotency_key' => $idempotencyKey,
                    'request_fingerprint' => $fingerprint,
                    'wallet_id' => $wallet->id,
                    'supplier_name' => $data->supplierName,
                    'description' => $data->description,
                    'effective_date' => $data->effectiveDate,
                    'subtotal' => $subtotal,
                    'shipping_amount' => $data->shippingAmount,
                    'total' => $subtotal + $data->shippingAmount,
                    'financial_transaction_id' => $financial->id,
                    'inventory_transaction_id' => $inventory?->id,
                    'created_by' => $adminId,
                ]);

                $movementByItem = $inventory?->movements->keyBy('inventory_item_id') ?? collect();
                foreach ($normalized as $entry) {
                    $movement = $movementByItem->get($entry['item']->id);
                    $purchase->lines()->create([
                        'inventory_item_id' => $entry['item']->id,
                        'purchase_presentation_id' => $entry['presentation']?->id,
                        'input_unit_id' => $entry['inputUnit']?->id,
                        'quantity' => $entry['quantity'], 'base_quantity' => $entry['baseQuantity'],
                        'price_mode' => $entry['line']->priceMode, 'price_amount' => $entry['line']->priceAmount,
                        'line_total' => $entry['lineTotal'], 'item_name' => $entry['item']->name,
                        'category_name' => $entry['item']->category->name, 'tracks_stock' => $entry['item']->tracks_stock,
                        'base_unit_code' => $entry['item']->baseUnit->code,
                        'input_unit_code' => $entry['inputUnit']?->code,
                        'input_unit_factor' => $entry['inputUnit']?->factor_to_base,
                        'presentation_name' => $entry['presentation']?->name,
                        'presentation_content_quantity' => $entry['presentation']?->content_quantity,
                        'presentation_unit_code' => $entry['presentation']?->contentUnit->code,
                        'presentation_unit_factor' => $entry['presentation']?->contentUnit->factor_to_base,
                        'stock_movement_id' => $movement?->id,
                        'inventory_cost_layer_id' => $movement?->costLayer?->id,
                    ]);
                }

                return $this->load($purchase);
            });
        } catch (Throwable $exception) {
            Log::warning('[Management] purchase.failed', ['module' => 'Management', 'admin_id' => $adminId,
                'idempotency_key' => $idempotencyKey, 'exception' => $exception::class]);
            throw $exception;
        }

        Log::info('[Management] purchase.created', ['module' => 'Management', 'purchase_id' => $purchase->id,
            'financial_transaction_id' => $purchase->financial_transaction_id,
            'inventory_transaction_id' => $purchase->inventory_transaction_id,
            'wallet_id' => $purchase->wallet_id, 'total' => $purchase->total, 'admin_id' => $adminId]);

        return $purchase;
    }

    private function fingerprint(PurchaseData $data): string
    {
        $payload = ['wallet_id' => $data->walletId, 'effective_date' => $data->effectiveDate->toDateString(),
            'supplier_name' => $data->supplierName, 'description' => $data->description,
            'shipping_amount' => $data->shippingAmount,
            'lines' => array_map(fn ($line): array => ['inventory_item_id' => $line->inventoryItemId,
                'purchase_presentation_id' => $line->purchasePresentationId, 'input_unit_id' => $line->inputUnitId,
                'quantity' => DecimalMath::normalize($line->quantity, 6), 'price_mode' => $line->priceMode->value, 'price_amount' => $line->priceAmount], $data->lines)];

        return hash_hmac('sha256', json_encode($payload, JSON_THROW_ON_ERROR), (string) config('app.key'));
    }

    private function load(Purchase $purchase): Purchase
    {
        return $purchase->load(['wallet', 'creator', 'lines', 'financialTransaction.entries.wallet',
            'financialTransaction.attachments', 'inventoryTransaction.movements.item.baseUnit', 'inventoryTransaction.costLayers', 'reversal']);
    }
}
