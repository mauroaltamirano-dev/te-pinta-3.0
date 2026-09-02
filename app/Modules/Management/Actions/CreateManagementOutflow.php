<?php

namespace App\Modules\Management\Actions;

use App\Modules\Management\Data\ManagementOutflowData;
use App\Modules\Management\Enums\FinancialTransactionType;
use App\Modules\Management\Models\FinancialTransaction;
use App\Modules\Management\Models\ManagementOutflow;
use App\Modules\Management\Models\ManagementOutflowCategory;
use App\Modules\Management\Models\Wallet;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

final class CreateManagementOutflow
{
    public function execute(ManagementOutflowData $data, int $adminId): ManagementOutflow
    {
        return DB::transaction(function () use ($data, $adminId): ManagementOutflow {
            $wallet = Wallet::query()->lockForUpdate()->findOrFail($data->walletId);
            if ($wallet->archived_at !== null) {
                throw ValidationException::withMessages(['wallet_id' => 'La billetera archivada no puede recibir egresos nuevos.']);
            }

            $category = ManagementOutflowCategory::query()->lockForUpdate()->findOrFail($data->categoryId);
            if (! $category->is_active) {
                throw ValidationException::withMessages(['category_id' => 'La categoría seleccionada no está activa.']);
            }
            if ($category->type !== $data->type) {
                throw ValidationException::withMessages(['category_id' => 'La categoría no corresponde al tipo de egreso.']);
            }

            $transaction = FinancialTransaction::query()->create([
                'type' => FinancialTransactionType::ManagementOutflow,
                'description' => $data->description,
                'effective_date' => $data->effectiveDate,
                'created_by' => $adminId,
            ]);
            $transaction->entries()->create(['wallet_id' => $wallet->id, 'amount' => -$data->amount]);

            $outflow = ManagementOutflow::query()->create([
                'type' => $data->type,
                'category_id' => $category->id,
                'wallet_id' => $wallet->id,
                'financial_transaction_id' => $transaction->id,
                'description' => $data->description,
                'counterparty' => $data->counterparty,
                'amount' => $data->amount,
                'effective_date' => $data->effectiveDate,
                'created_by' => $adminId,
            ]);

            Log::info('[Management] outflow.created', [
                'module' => 'Management',
                'management_outflow_id' => $outflow->id,
                'financial_transaction_id' => $transaction->id,
                'wallet_id' => $wallet->id,
                'admin_id' => $adminId,
                'type' => $data->type->value,
                'category_id' => $category->id,
            ]);

            return $outflow->load('category', 'wallet', 'financialTransaction.entries.wallet', 'creator');
        });
    }
}
