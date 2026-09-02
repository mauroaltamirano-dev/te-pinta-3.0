<?php

namespace App\Modules\Management\Http\Resources;

use App\Modules\Management\Models\FinancialTransaction;
use App\Modules\Management\Models\LedgerEntry;
use App\Modules\Management\Models\ManagementDistribution;
use App\Modules\Management\Models\ManagementOrderPayment;
use App\Modules\Management\Models\ManagementOutflow;
use App\Modules\Management\Models\ManagementTransfer;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin FinancialTransaction */
final class AdminFinancialTransactionResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $outflow = $this->managementOutflow;
        $isOutflowReversal = false;
        if ($outflow === null && $this->reversalOf !== null) {
            $outflow = $this->reversalOf->managementOutflow;
            $isOutflowReversal = $outflow !== null;
        }
        $transfer = $this->managementTransfer;
        $isTransferReversal = false;
        if ($transfer === null && $this->reversalOf !== null) {
            $transfer = $this->reversalOf->managementTransfer;
            $isTransferReversal = $transfer !== null;
        }
        $distribution = $this->managementDistribution;
        $isDistributionReversal = false;
        if ($distribution === null && $this->reversalOf !== null) {
            $distribution = $this->reversalOf->managementDistribution;
            $isDistributionReversal = $distribution !== null;
        }
        $orderPayment = $this->managementOrderPayment;
        $isOrderPaymentReversal = false;
        if ($orderPayment === null && $this->reversalOf !== null) {
            $orderPayment = $this->reversalOf->managementOrderPayment;
            $isOrderPaymentReversal = $orderPayment !== null;
        }

        return [
            'id' => $this->id,
            'type' => $this->type->value,
            'description' => $this->description,
            'effective_date' => $this->effective_date->toDateString(),
            'created_at' => $this->created_at?->toIso8601String(),
            'reversal_of_id' => $this->reversal_of_id,
            'reversal_transaction_id' => $this->reversal?->id,
            'attachments_count' => (int) ($this->attachments_count ?? 0),
            'created_by' => ['id' => $this->creator->id, 'name' => $this->creator->name],
            'entries' => $this->entries->map(fn (LedgerEntry $entry): array => [
                'id' => $entry->id,
                'amount' => (int) $entry->amount,
                'wallet' => [
                    'id' => $entry->wallet->id,
                    'name' => $entry->wallet->name,
                    'archived_at' => $entry->wallet->archived_at?->toIso8601String(),
                ],
            ])->all(),
            'management_outflow' => $outflow instanceof ManagementOutflow ? [
                'id' => $outflow->id,
                'type' => $outflow->type->value,
                'category' => [
                    'id' => $outflow->category->id,
                    'code' => $outflow->category->code,
                    'name' => $outflow->category->name,
                    'is_active' => $outflow->category->is_active,
                ],
                'description' => $outflow->description,
                'counterparty' => $outflow->counterparty,
                'amount' => (int) $outflow->amount,
                'effective_date' => $outflow->effective_date->toDateString(),
                'created_at' => $outflow->created_at?->toIso8601String(),
                'financial_transaction_id' => $outflow->financial_transaction_id,
                'is_reversal' => $isOutflowReversal,
                'reversed' => $isOutflowReversal || $this->reversal !== null,
            ] : null,
            'management_transfer' => $transfer instanceof ManagementTransfer ? [
                'id' => $transfer->id,
                'source_wallet' => [
                    'id' => $transfer->sourceWallet->id,
                    'name' => $transfer->sourceWallet->name,
                    'archived_at' => $transfer->sourceWallet->archived_at?->toIso8601String(),
                ],
                'destination_wallet' => [
                    'id' => $transfer->destinationWallet->id,
                    'name' => $transfer->destinationWallet->name,
                    'archived_at' => $transfer->destinationWallet->archived_at?->toIso8601String(),
                ],
                'description' => $transfer->description,
                'amount' => (int) $transfer->amount,
                'effective_date' => $transfer->effective_date->toDateString(),
                'created_at' => $transfer->created_at?->toIso8601String(),
                'financial_transaction_id' => $transfer->financial_transaction_id,
                'is_reversal' => $isTransferReversal,
                'reversed' => $isTransferReversal || $this->reversal !== null,
            ] : null,
            'management_distribution' => $distribution instanceof ManagementDistribution ? [
                'id' => $distribution->id,
                'source_wallet' => [
                    'id' => $distribution->sourceWallet->id,
                    'name' => $distribution->sourceWallet->name,
                    'archived_at' => $distribution->sourceWallet->archived_at?->toIso8601String(),
                ],
                'rule' => [
                    'id' => $distribution->ruleVersion->rule->id,
                    'name' => $distribution->ruleVersion->rule->name,
                ],
                'rule_version' => [
                    'id' => $distribution->ruleVersion->id,
                    'version_number' => $distribution->ruleVersion->version_number,
                ],
                'description' => $distribution->description,
                'amount' => (int) $distribution->amount,
                'effective_date' => $distribution->effective_date->toDateString(),
                'created_at' => $distribution->created_at?->toIso8601String(),
                'financial_transaction_id' => $distribution->financial_transaction_id,
                'management_order_payment' => $distribution->managementOrderPayment instanceof ManagementOrderPayment ? [
                    'id' => $distribution->managementOrderPayment->id,
                    'financial_transaction_id' => $distribution->managementOrderPayment->financial_transaction_id,
                    'order' => [
                        'id' => $distribution->managementOrderPayment->order->id,
                        'public_id' => $distribution->managementOrderPayment->order->public_id,
                    ],
                ] : null,
                'is_reversal' => $isDistributionReversal,
                'reversed' => $isDistributionReversal || $this->reversal !== null,
                'allocations' => $distribution->allocations->map(fn ($allocation): array => [
                    'id' => $allocation->id,
                    'wallet' => [
                        'id' => $allocation->wallet->id,
                        'name' => $allocation->wallet->name,
                        'archived_at' => $allocation->wallet->archived_at?->toIso8601String(),
                    ],
                    'wallet_name' => $allocation->wallet_name,
                    'percentage_bps' => $allocation->percentage_bps,
                    'amount' => (int) $allocation->amount,
                    'sort_order' => $allocation->sort_order,
                ])->all(),
            ] : null,
            'management_order_payment' => $orderPayment instanceof ManagementOrderPayment ? [
                'id' => $orderPayment->id,
                'order' => [
                    'id' => $orderPayment->order->id,
                    'public_id' => $orderPayment->order->public_id,
                    'customer_name' => $orderPayment->order->customer_name_snapshot,
                ],
                'destination_wallet' => [
                    'id' => $orderPayment->destinationWallet->id,
                    'name' => $orderPayment->destinationWallet->name,
                    'archived_at' => $orderPayment->destinationWallet->archived_at?->toIso8601String(),
                ],
                'amount' => (int) $orderPayment->amount,
                'effective_date' => $orderPayment->effective_date->toDateString(),
                'created_at' => $orderPayment->created_at?->toIso8601String(),
                'financial_transaction_id' => $orderPayment->financial_transaction_id,
                'distribution_status' => $orderPayment->distribution === null
                    ? 'pending'
                    : ($orderPayment->distribution->financialTransaction->reversal === null ? 'distributed' : 'reversed'),
                'distribution' => $orderPayment->distribution !== null ? [
                    'id' => $orderPayment->distribution->id,
                    'financial_transaction_id' => $orderPayment->distribution->financial_transaction_id,
                    'rule' => [
                        'id' => $orderPayment->distribution->ruleVersion->rule->id,
                        'name' => $orderPayment->distribution->ruleVersion->rule->name,
                    ],
                    'rule_version' => [
                        'id' => $orderPayment->distribution->ruleVersion->id,
                        'version_number' => $orderPayment->distribution->ruleVersion->version_number,
                    ],
                    'reversed' => $orderPayment->distribution->financialTransaction->reversal !== null,
                ] : null,
                'is_reversal' => $isOrderPaymentReversal,
                'reversed' => $isOrderPaymentReversal || $this->reversal !== null,
            ] : null,
        ];
    }
}
