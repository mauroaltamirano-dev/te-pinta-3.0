<?php

namespace App\Modules\Management\Actions;

use App\Modules\Management\Data\ManualTransactionData;
use App\Modules\Management\Models\FinancialTransaction;
use App\Modules\Management\Models\Wallet;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

final class CreateManualTransaction
{
    public function execute(ManualTransactionData $data, int $adminId): FinancialTransaction
    {
        return DB::transaction(function () use ($data, $adminId): FinancialTransaction {
            $wallet = Wallet::query()->lockForUpdate()->findOrFail($data->walletId);
            if ($wallet->archived_at !== null) {
                throw ValidationException::withMessages(['wallet_id' => 'La billetera archivada no puede recibir movimientos nuevos.']);
            }

            $transaction = FinancialTransaction::query()->create([
                'type' => $data->type,
                'description' => $data->description,
                'effective_date' => $data->effectiveDate,
                'created_by' => $adminId,
            ]);
            $transaction->entries()->create(['wallet_id' => $wallet->id, 'amount' => $data->type->signedAmount($data->amount)]);
            Log::info('[Management] transaction.created', [
                'module' => 'Management', 'financial_transaction_id' => $transaction->id,
                'wallet_id' => $wallet->id, 'type' => $data->type->value, 'admin_id' => $adminId,
            ]);

            return $transaction->load('entries.wallet', 'creator');
        });
    }
}
