<?php

namespace App\Modules\Management\Queries;

use App\Modules\Management\Enums\FinancialTransactionType;
use App\Modules\Management\Enums\ManagementOutflowType;
use App\Modules\Management\Models\FinancialTransaction;
use App\Modules\Management\Models\Wallet;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

final class BuildManagementOverview
{
    public function __construct(private readonly BuildManagementFinancialSummary $summary) {}

    /**
     * @param  array{history_from?: string, history_to?: string, transaction_id?: ?int, wallet_id: ?int, type: ?string, category_id: ?int, from: string, to: string}  $filters
     * @return array{wallets: Collection<int, Wallet>, transactions: LengthAwarePaginator<int, FinancialTransaction>, summary: array<string, int>}
     */
    public function execute(array $filters): array
    {
        $transactionId = $filters['transaction_id'] ?? null;
        $wallets = Wallet::query()
            ->withSum('ledgerEntries as balance', 'amount')
            ->orderByRaw('archived_at IS NOT NULL')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        $transactions = FinancialTransaction::query()
            ->withCount('attachments')
            ->with([
                'entries.wallet',
                'purchase:id,financial_transaction_id',
                'reversalOf.purchase:id,financial_transaction_id',
                'creator',
                'reversal:id,reversal_of_id',
                'managementOutflow.category',
                'reversalOf.managementOutflow.category',
                'managementTransfer.sourceWallet',
                'managementTransfer.destinationWallet',
                'reversalOf.managementTransfer.sourceWallet',
                'reversalOf.managementTransfer.destinationWallet',
                'managementDistribution.ruleVersion.rule',
                'managementDistribution.sourceWallet',
                'managementDistribution.allocations.wallet',
                'managementDistribution.managementOrderPayment.order',
                'reversalOf.managementDistribution.ruleVersion.rule',
                'reversalOf.managementDistribution.sourceWallet',
                'reversalOf.managementDistribution.allocations.wallet',
                'reversalOf.managementDistribution.managementOrderPayment.order',
                'managementOrderPayment.order',
                'managementOrderPayment.destinationWallet',
                'managementOrderPayment.distribution.ruleVersion.rule',
                'managementOrderPayment.distribution.financialTransaction.reversal',
                'reversalOf.managementOrderPayment.order',
                'reversalOf.managementOrderPayment.destinationWallet',
                'reversalOf.managementOrderPayment.distribution.ruleVersion.rule',
                'reversalOf.managementOrderPayment.distribution.financialTransaction.reversal',
            ])
            ->when($transactionId !== null, fn (Builder $query) => $query->whereKey($transactionId))
            ->when($filters['wallet_id'] !== null, fn (Builder $query) => $query->whereHas(
                'entries', fn (Builder $entries) => $entries->where('wallet_id', $filters['wallet_id'])
            ))
            ->when($filters['type'] !== null, function (Builder $query) use ($filters): void {
                if ($filters['type'] === FinancialTransactionType::Transfer->value) {
                    $query->where(function (Builder $semantic): void {
                        $semantic->where('type', FinancialTransactionType::Transfer)
                            ->orWhereHas('reversalOf.managementTransfer');
                    });
                } elseif ($filters['type'] === FinancialTransactionType::Distribution->value) {
                    $query->where(function (Builder $semantic): void {
                        $semantic->where('type', FinancialTransactionType::Distribution)
                            ->orWhereHas('reversalOf.managementDistribution');
                    });
                } elseif ($filters['type'] === FinancialTransactionType::OrderPayment->value) {
                    $query->where(function (Builder $semantic): void {
                        $semantic->where('type', FinancialTransactionType::OrderPayment)
                            ->orWhereHas('reversalOf.managementOrderPayment');
                    });
                } elseif (ManagementOutflowType::tryFrom($filters['type']) !== null) {
                    $query->where(function (Builder $semantic) use ($filters): void {
                        $semantic->whereHas('managementOutflow', fn (Builder $outflow) => $outflow->where('type', $filters['type']))
                            ->orWhereHas('reversalOf.managementOutflow', fn (Builder $outflow) => $outflow->where('type', $filters['type']));
                        if ($filters['type'] === ManagementOutflowType::Purchase->value) {
                            $semantic->orWhere('type', FinancialTransactionType::Purchase)
                                ->orWhereHas('reversalOf', fn (Builder $original) => $original->where('type', FinancialTransactionType::Purchase));
                        }
                    });
                } else {
                    $query->where('type', $filters['type']);
                }
            })
            ->when($filters['category_id'] !== null, function (Builder $query) use ($filters): void {
                $query->where(function (Builder $semantic) use ($filters): void {
                    $semantic->whereHas('managementOutflow', fn (Builder $outflow) => $outflow->where('category_id', $filters['category_id']))
                        ->orWhereHas('reversalOf.managementOutflow', fn (Builder $outflow) => $outflow->where('category_id', $filters['category_id']));
                });
            })
            ->whereBetween('effective_date', [$filters['history_from'] ?? $filters['from'], $filters['history_to'] ?? $filters['to']])
            ->orderByDesc('effective_date')
            ->orderByDesc('id')
            ->paginate(25)
            ->withQueryString();

        return [
            'wallets' => $wallets,
            'transactions' => $transactions,
            'summary' => $this->summary->execute($filters['from'], $filters['to']),
        ];
    }

    /** @return array{transaction_id: ?int, wallet_id: ?int, type: ?string, category_id: ?int, from: string, to: string} */
    public static function defaultFilters(): array
    {
        $today = CarbonImmutable::today(config('orders.timezone'));

        return ['transaction_id' => null, 'wallet_id' => null, 'type' => null, 'category_id' => null, 'from' => $today->startOfMonth()->toDateString(), 'to' => $today->toDateString()];
    }
}
