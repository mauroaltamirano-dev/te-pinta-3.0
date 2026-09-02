<?php

namespace App\Modules\Management\Actions;

use App\Modules\Management\Data\ManagementDistributionData;
use App\Modules\Management\Models\DistributionRule;
use App\Modules\Management\Models\FinancialTransaction;
use App\Modules\Management\Models\ManagementDistribution;
use App\Modules\Management\Models\ManagementOrderPayment;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Throwable;

final readonly class ApplyDistributionToOrderPayment
{
    public function __construct(private CreateManagementDistribution $createDistribution) {}

    public function execute(ManagementOrderPayment $payment, int $adminId): ?ManagementDistribution
    {
        try {
            return DB::transaction(function () use ($payment, $adminId): ?ManagementDistribution {
                // Match ReverseFinancialTransaction's lock order so a retry cannot race a payment reversal.
                $paymentTransaction = FinancialTransaction::query()
                    ->with('reversal')
                    ->lockForUpdate()
                    ->findOrFail($payment->financial_transaction_id);
                $lockedPayment = ManagementOrderPayment::query()
                    ->with('distribution')
                    ->lockForUpdate()
                    ->findOrFail($payment->id);

                if ($paymentTransaction->reversal !== null) {
                    throw ValidationException::withMessages([
                        'transaction' => 'Un pago de pedido revertido no puede distribuirse.',
                    ]);
                }

                if ($lockedPayment->distribution !== null) {
                    return $lockedPayment->distribution;
                }

                $rule = DistributionRule::query()
                    ->where('active', true)
                    ->lockForUpdate()
                    ->first();
                if (! $rule instanceof DistributionRule) {
                    Log::info('[Management] order_payment.distribution_skipped', [
                        'module' => 'Management',
                        'management_order_payment_id' => $lockedPayment->id,
                        'order_id' => $lockedPayment->order_id,
                        'wallet_id' => $lockedPayment->destination_wallet_id,
                        'admin_id' => $adminId,
                        'reason' => 'no_active_rule',
                    ]);

                    return null;
                }

                $version = $rule->versions()->orderByDesc('version_number')->firstOrFail();
                $distribution = $this->createDistribution->execute(new ManagementDistributionData(
                    sourceWalletId: $lockedPayment->destination_wallet_id,
                    ruleVersionId: $version->id,
                    amount: (int) $lockedPayment->amount,
                    effectiveDate: $lockedPayment->effective_date,
                    description: null,
                    managementOrderPaymentId: $lockedPayment->id,
                ), $adminId);

                Log::info('[Management] order_payment.distribution_created', [
                    'module' => 'Management',
                    'management_order_payment_id' => $lockedPayment->id,
                    'order_id' => $lockedPayment->order_id,
                    'management_distribution_id' => $distribution->id,
                    'financial_transaction_id' => $distribution->financial_transaction_id,
                    'rule_version_id' => $version->id,
                    'wallet_id' => $lockedPayment->destination_wallet_id,
                    'admin_id' => $adminId,
                ]);

                return $distribution;
            });
        } catch (Throwable $exception) {
            Log::error('[Management] order_payment.distribution_failed', [
                'module' => 'Management',
                'management_order_payment_id' => $payment->id,
                'order_id' => $payment->order_id,
                'wallet_id' => $payment->destination_wallet_id,
                'admin_id' => $adminId,
                'exception' => $exception::class,
            ]);

            throw $exception;
        }
    }
}
