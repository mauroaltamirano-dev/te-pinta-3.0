<?php

namespace App\Modules\Management\Actions;

use App\Modules\Management\Enums\FinancialTransactionType;
use App\Modules\Management\Models\FinancialTransaction;
use App\Modules\Management\Models\ManagementOrderPayment;
use App\Modules\Management\Models\Wallet;
use App\Modules\Orders\Enums\PaymentStatus;
use App\Modules\Orders\Models\Order;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

final class CreateManagementOrderPayment
{
    public function execute(int $orderId, int $amount, CarbonImmutable $effectiveDate, int $adminId): ManagementOrderPayment
    {
        if ($amount <= 0) {
            throw ValidationException::withMessages(['amount' => 'El importe del pago debe ser mayor que cero.']);
        }

        return DB::transaction(function () use ($orderId, $amount, $effectiveDate, $adminId): ManagementOrderPayment {
            $order = Order::query()->whereKey($orderId)->lockForUpdate()->first();
            if (! $order instanceof Order || $order->payment_status !== PaymentStatus::Paid) {
                throw ValidationException::withMessages([
                    'payment_status' => 'El pedido debe existir y estar confirmado como pagado.',
                ]);
            }
            if ((int) $order->total !== $amount) {
                throw ValidationException::withMessages([
                    'payment_status' => 'El importe confirmado no coincide con el total actual del pedido.',
                ]);
            }

            $existing = ManagementOrderPayment::query()->where('order_id', $orderId)->first();
            if ($existing !== null) {
                return $this->existingOrFail($existing, $amount);
            }

            $destination = Wallet::query()
                ->where('is_order_payment_default', true)
                ->lockForUpdate()
                ->first();
            if (! $destination instanceof Wallet || $destination->archived_at !== null) {
                throw ValidationException::withMessages([
                    'payment_status' => 'Configurá una billetera activa para cobros de pedidos antes de confirmar el pago.',
                ]);
            }

            // Serializing on the single default wallet makes the unique order check deterministic
            // even when the same confirmation is delivered concurrently.
            $existing = ManagementOrderPayment::query()->where('order_id', $orderId)->first();
            if ($existing !== null) {
                return $this->existingOrFail($existing, $amount);
            }

            $transaction = FinancialTransaction::query()->create([
                'type' => FinancialTransactionType::OrderPayment,
                'description' => null,
                'effective_date' => $effectiveDate,
                'created_by' => $adminId,
            ]);
            $transaction->entries()->create([
                'wallet_id' => $destination->id,
                'amount' => $amount,
            ]);
            $payment = ManagementOrderPayment::query()->create([
                'order_id' => $orderId,
                'destination_wallet_id' => $destination->id,
                'financial_transaction_id' => $transaction->id,
                'amount' => $amount,
                'effective_date' => $effectiveDate,
                'created_by' => $adminId,
            ]);

            Log::info('[Management] order_payment.created', [
                'module' => 'Management',
                'order_id' => $orderId,
                'management_order_payment_id' => $payment->id,
                'financial_transaction_id' => $transaction->id,
                'wallet_id' => $destination->id,
                'admin_id' => $adminId,
            ]);

            return $payment->load('order', 'destinationWallet', 'financialTransaction.entries.wallet', 'creator');
        });
    }

    private function existingOrFail(ManagementOrderPayment $existing, int $amount): ManagementOrderPayment
    {
        if ((int) $existing->amount !== $amount) {
            throw ValidationException::withMessages([
                'payment_status' => 'El pedido ya tiene un pago financiero por otro importe; requiere reversión y reconciliación explícita.',
            ]);
        }

        return $existing->load('order', 'destinationWallet', 'financialTransaction.entries.wallet', 'creator');
    }
}
