<?php

namespace App\Modules\Management\Actions;

use App\Modules\Management\Data\ManagementDistributionData;
use App\Modules\Management\Enums\FinancialTransactionType;
use App\Modules\Management\Models\DistributionRule;
use App\Modules\Management\Models\DistributionRuleVersion;
use App\Modules\Management\Models\FinancialTransaction;
use App\Modules\Management\Models\ManagementDistribution;
use App\Modules\Management\Models\ManagementOrderPayment;
use App\Modules\Management\Models\Wallet;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

final class CreateManagementDistribution
{
    public function execute(ManagementDistributionData $data, int $adminId): ManagementDistribution
    {
        return DB::transaction(function () use ($data, $adminId): ManagementDistribution {
            $version = DistributionRuleVersion::query()->lockForUpdate()->findOrFail($data->ruleVersionId);
            $rule = DistributionRule::query()->lockForUpdate()->findOrFail($version->distribution_rule_id);
            if (! $rule->active) {
                throw ValidationException::withMessages(['rule_version_id' => 'La regla seleccionada no está activa.']);
            }

            if ($data->managementOrderPaymentId !== null) {
                $payment = ManagementOrderPayment::query()->lockForUpdate()->findOrFail($data->managementOrderPaymentId);
                if ($payment->distribution()->exists()) {
                    throw ValidationException::withMessages([
                        'management_order_payment_id' => 'El pago ya tiene una distribución asociada.',
                    ]);
                }
                if ($payment->destination_wallet_id !== $data->sourceWalletId
                    || (int) $payment->amount !== $data->amount
                    || ! $payment->effective_date->equalTo($data->effectiveDate)) {
                    throw ValidationException::withMessages([
                        'management_order_payment_id' => 'La distribución debe conservar la billetera, el monto y la fecha efectiva del pago.',
                    ]);
                }
            }

            $allocations = $version->allocations()->get();
            $destinationWalletIds = $allocations->pluck('wallet_id');
            if ($destinationWalletIds->contains($data->sourceWalletId)) {
                throw ValidationException::withMessages(['source_wallet_id' => 'La billetera de origen debe ser distinta de todos los destinos.']);
            }

            $walletIds = $destinationWalletIds->push($data->sourceWalletId)->unique();
            $wallets = Wallet::query()->whereKey($walletIds)
                ->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            $sourceWallet = $wallets->get($data->sourceWalletId);
            if (! $sourceWallet instanceof Wallet || $sourceWallet->archived_at !== null) {
                throw ValidationException::withMessages(['source_wallet_id' => 'La billetera de origen debe existir y estar activa.']);
            }
            if ($wallets->count() !== $allocations->count() + 1 || $wallets->contains(fn (Wallet $wallet): bool => $wallet->archived_at !== null)) {
                throw ValidationException::withMessages(['rule_version_id' => 'La versión contiene una billetera archivada y no puede aplicarse.']);
            }

            $parts = $allocations->map(fn ($allocation): int => intdiv($data->amount * $allocation->percentage_bps, 10000));
            $remainder = $data->amount - $parts->sum();
            if ($parts->isNotEmpty()) {
                $lastIndex = $parts->keys()->last();
                $parts->put($lastIndex, $parts->get($lastIndex) + $remainder);
            }
            if ($parts->contains(fn (int $amount): bool => $amount <= 0)) {
                throw ValidationException::withMessages([
                    'amount' => 'El monto es demasiado bajo: cada destino debe recibir al menos un peso.',
                ]);
            }

            $transaction = FinancialTransaction::query()->create([
                'type' => FinancialTransactionType::Distribution,
                'description' => $data->description,
                'effective_date' => $data->effectiveDate,
                'created_by' => $adminId,
            ]);
            $transaction->entries()->create(['wallet_id' => $sourceWallet->id, 'amount' => -$data->amount]);
            $transaction->entries()->createMany($allocations->values()->map(
                fn ($allocation, int $index): array => ['wallet_id' => $allocation->wallet_id, 'amount' => $parts[$index]]
            )->all());

            $distribution = ManagementDistribution::query()->create([
                'source_wallet_id' => $sourceWallet->id,
                'management_order_payment_id' => $data->managementOrderPaymentId,
                'rule_version_id' => $version->id,
                'financial_transaction_id' => $transaction->id,
                'amount' => $data->amount,
                'effective_date' => $data->effectiveDate,
                'description' => $data->description,
                'created_by' => $adminId,
            ]);
            $distribution->allocations()->createMany($allocations->values()->map(
                fn ($allocation, int $index): array => [
                    'wallet_id' => $allocation->wallet_id,
                    'wallet_name' => $wallets->get($allocation->wallet_id)->name,
                    'percentage_bps' => $allocation->percentage_bps,
                    'amount' => $parts[$index],
                    'sort_order' => $allocation->sort_order,
                ]
            )->all());

            Log::info('[Management] distribution.created', [
                'module' => 'Management',
                'management_distribution_id' => $distribution->id,
                'financial_transaction_id' => $transaction->id,
                'distribution_rule_id' => $rule->id,
                'rule_version_id' => $version->id,
                'source_wallet_id' => $sourceWallet->id,
                'admin_id' => $adminId,
            ]);

            return $distribution->load('sourceWallet', 'managementOrderPayment.order', 'ruleVersion.rule', 'allocations.wallet', 'financialTransaction.entries.wallet', 'creator');
        });
    }
}
