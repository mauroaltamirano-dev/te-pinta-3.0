<?php

namespace App\Modules\Management\Actions;

use App\Modules\Management\Data\ManagementTransferData;
use App\Modules\Management\Enums\FinancialTransactionType;
use App\Modules\Management\Models\FinancialTransaction;
use App\Modules\Management\Models\ManagementTransfer;
use App\Modules\Management\Models\Wallet;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

final class CreateManagementTransfer
{
    public function execute(ManagementTransferData $data, int $adminId): ManagementTransfer
    {
        if ($data->sourceWalletId === $data->destinationWalletId) {
            throw ValidationException::withMessages([
                'destination_wallet_id' => 'La billetera de destino debe ser diferente de la billetera de origen.',
            ]);
        }

        return DB::transaction(function () use ($data, $adminId): ManagementTransfer {
            // A single ascending lock order prevents opposite A→B / B→A transfers from deadlocking.
            $wallets = Wallet::query()
                ->whereKey([$data->sourceWalletId, $data->destinationWalletId])
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            $source = $wallets->get($data->sourceWalletId);
            $destination = $wallets->get($data->destinationWalletId);
            if (! $source instanceof Wallet) {
                throw ValidationException::withMessages(['source_wallet_id' => 'La billetera de origen no existe.']);
            }
            if (! $destination instanceof Wallet) {
                throw ValidationException::withMessages(['destination_wallet_id' => 'La billetera de destino no existe.']);
            }
            if ($source->archived_at !== null) {
                throw ValidationException::withMessages(['source_wallet_id' => 'La billetera de origen archivada no puede usarse en transferencias nuevas.']);
            }
            if ($destination->archived_at !== null) {
                throw ValidationException::withMessages(['destination_wallet_id' => 'La billetera de destino archivada no puede usarse en transferencias nuevas.']);
            }

            $transaction = FinancialTransaction::query()->create([
                'type' => FinancialTransactionType::Transfer,
                'description' => $data->description,
                'effective_date' => $data->effectiveDate,
                'created_by' => $adminId,
            ]);
            $transaction->entries()->createMany([
                ['wallet_id' => $source->id, 'amount' => -$data->amount],
                ['wallet_id' => $destination->id, 'amount' => $data->amount],
            ]);

            $transfer = ManagementTransfer::query()->create([
                'source_wallet_id' => $source->id,
                'destination_wallet_id' => $destination->id,
                'financial_transaction_id' => $transaction->id,
                'amount' => $data->amount,
                'effective_date' => $data->effectiveDate,
                'description' => $data->description,
                'created_by' => $adminId,
            ]);

            Log::info('[Management] transfer.created', [
                'module' => 'Management',
                'management_transfer_id' => $transfer->id,
                'financial_transaction_id' => $transaction->id,
                'source_wallet_id' => $source->id,
                'destination_wallet_id' => $destination->id,
                'admin_id' => $adminId,
            ]);

            return $transfer->load('sourceWallet', 'destinationWallet', 'financialTransaction.entries.wallet', 'creator');
        });
    }
}
