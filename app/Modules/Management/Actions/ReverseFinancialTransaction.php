<?php

namespace App\Modules\Management\Actions;

use App\Modules\Management\Data\ReversalData;
use App\Modules\Management\Enums\FinancialTransactionType;
use App\Modules\Management\Models\FinancialTransaction;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

final class ReverseFinancialTransaction
{
    public function execute(FinancialTransaction $original, ReversalData $data, int $adminId): FinancialTransaction
    {
        return DB::transaction(function () use ($original, $data, $adminId): FinancialTransaction {
            $locked = FinancialTransaction::query()->with([
                'entries',
                'managementOutflow',
                'managementTransfer',
                'managementDistribution',
                'managementOrderPayment.distribution.financialTransaction.reversal',
            ])->lockForUpdate()->findOrFail($original->id);
            if ($locked->type === FinancialTransactionType::Purchase) {
                throw ValidationException::withMessages(['transaction' => 'Este movimiento pertenece a una compra. Revertilo con "Revertir compra" desde el detalle de la compra.']);
            }
            if ($locked->type === FinancialTransactionType::Reversal) {
                throw ValidationException::withMessages(['transaction' => 'Una reversión no puede volver a revertirse.']);
            }
            if (FinancialTransaction::query()->where('reversal_of_id', $locked->id)->exists()) {
                throw ValidationException::withMessages(['transaction' => 'El movimiento ya fue revertido.']);
            }
            $paymentDistribution = $locked->managementOrderPayment?->distribution;
            if ($paymentDistribution !== null && $paymentDistribution->financialTransaction->reversal === null) {
                throw ValidationException::withMessages([
                    'transaction' => 'Revertí primero la distribución asociada y luego el pago del pedido.',
                ]);
            }

            $reversal = FinancialTransaction::query()->create([
                'type' => FinancialTransactionType::Reversal,
                'description' => $data->description,
                'effective_date' => $data->effectiveDate,
                'created_by' => $adminId,
                'reversal_of_id' => $locked->id,
            ]);
            foreach ($locked->entries as $entry) {
                $reversal->entries()->create(['wallet_id' => $entry->wallet_id, 'amount' => -((int) $entry->amount)]);
            }
            Log::info('[Management] transaction.reversed', [
                'module' => 'Management', 'financial_transaction_id' => $reversal->id,
                'reversal_of_id' => $locked->id, 'admin_id' => $adminId,
            ]);
            if ($locked->managementOutflow !== null) {
                Log::info('[Management] outflow.reversed', [
                    'module' => 'Management',
                    'management_outflow_id' => $locked->managementOutflow->id,
                    'financial_transaction_id' => $reversal->id,
                    'reversal_of_id' => $locked->id,
                    'wallet_id' => $locked->managementOutflow->wallet_id,
                    'admin_id' => $adminId,
                    'type' => $locked->managementOutflow->type->value,
                    'category_id' => $locked->managementOutflow->category_id,
                ]);
            }
            if ($locked->managementTransfer !== null) {
                Log::info('[Management] transfer.reversed', [
                    'module' => 'Management',
                    'management_transfer_id' => $locked->managementTransfer->id,
                    'financial_transaction_id' => $reversal->id,
                    'reversal_of_id' => $locked->id,
                    'source_wallet_id' => $locked->managementTransfer->source_wallet_id,
                    'destination_wallet_id' => $locked->managementTransfer->destination_wallet_id,
                    'admin_id' => $adminId,
                ]);
            }
            if ($locked->managementDistribution !== null) {
                Log::info('[Management] distribution.reversed', [
                    'module' => 'Management',
                    'management_distribution_id' => $locked->managementDistribution->id,
                    'financial_transaction_id' => $reversal->id,
                    'reversal_of_id' => $locked->id,
                    'rule_version_id' => $locked->managementDistribution->rule_version_id,
                    'source_wallet_id' => $locked->managementDistribution->source_wallet_id,
                    'admin_id' => $adminId,
                ]);
            }
            if ($locked->managementOrderPayment !== null) {
                Log::info('[Management] order_payment.reversed', [
                    'module' => 'Management',
                    'management_order_payment_id' => $locked->managementOrderPayment->id,
                    'order_id' => $locked->managementOrderPayment->order_id,
                    'financial_transaction_id' => $reversal->id,
                    'reversal_of_id' => $locked->id,
                    'wallet_id' => $locked->managementOrderPayment->destination_wallet_id,
                    'admin_id' => $adminId,
                ]);
            }

            return $reversal->load('entries.wallet', 'creator');
        });
    }
}
