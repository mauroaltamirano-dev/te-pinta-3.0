<?php

namespace App\Modules\Management\Queries;

use App\Modules\Management\Enums\FinancialTransactionType;
use App\Modules\Management\Enums\ManagementOutflowType;
use App\Modules\Management\Models\FinancialTransaction;
use App\Modules\Management\Models\LedgerEntry;
use App\Modules\Management\Models\Wallet;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

final class BuildManagementOverview
{
    /**
     * @param  array{transaction_id?: ?int, wallet_id: ?int, type: ?string, category_id: ?int, from: string, to: string}  $filters
     * @return array{wallets: Collection<int, Wallet>, transactions: LengthAwarePaginator<int, FinancialTransaction>, summary: array{total_balance: int, period_income: int, period_expense: int, period_operating_outflow: int, period_personal_withdrawal: int, period_distribution: int}}
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
            ->whereBetween('effective_date', [$filters['from'], $filters['to']])
            ->orderByDesc('effective_date')
            ->orderByDesc('id')
            ->paginate(25)
            ->withQueryString();

        $periodTotals = DB::table('ledger_entries')
            ->join('financial_transactions', 'financial_transactions.id', '=', 'ledger_entries.financial_transaction_id')
            ->leftJoin('management_order_payments as direct_order_payments', 'direct_order_payments.financial_transaction_id', '=', 'financial_transactions.id')
            ->leftJoin('management_order_payments as reversed_order_payments', 'reversed_order_payments.financial_transaction_id', '=', 'financial_transactions.reversal_of_id')
            ->whereBetween('financial_transactions.effective_date', [$filters['from'], $filters['to']])
            ->where(function ($query): void {
                $query->whereIn('financial_transactions.type', [
                    FinancialTransactionType::ManualIncome->value,
                    FinancialTransactionType::ManualExpense->value,
                ])->orWhereNotNull('direct_order_payments.id')
                    ->orWhereNotNull('reversed_order_payments.id');
            })
            ->selectRaw(
                'COALESCE(SUM(CASE WHEN financial_transactions.type = ? OR direct_order_payments.id IS NOT NULL OR reversed_order_payments.id IS NOT NULL THEN ledger_entries.amount ELSE 0 END), 0) AS income',
                [FinancialTransactionType::ManualIncome->value],
            )
            ->selectRaw('COALESCE(SUM(CASE WHEN financial_transactions.type = ? THEN -ledger_entries.amount ELSE 0 END), 0) AS expense', [FinancialTransactionType::ManualExpense->value])
            ->first(['income', 'expense']);

        $outflowTotals = DB::table('ledger_entries as entries')
            ->join('financial_transactions as transactions', 'transactions.id', '=', 'entries.financial_transaction_id')
            ->leftJoin('management_outflows as direct_outflows', 'direct_outflows.financial_transaction_id', '=', 'transactions.id')
            ->leftJoin('management_outflows as reversed_outflows', 'reversed_outflows.financial_transaction_id', '=', 'transactions.reversal_of_id')
            ->whereBetween('transactions.effective_date', [$filters['from'], $filters['to']])
            ->where(function ($query): void {
                $query->whereNotNull('direct_outflows.id')->orWhereNotNull('reversed_outflows.id');
            })
            ->selectRaw(
                'COALESCE(SUM(CASE WHEN COALESCE(direct_outflows.type, reversed_outflows.type) IN (?, ?) THEN -entries.amount ELSE 0 END), 0) AS operating_outflow',
                [ManagementOutflowType::Purchase->value, ManagementOutflowType::Expense->value],
            )
            ->selectRaw(
                'COALESCE(SUM(CASE WHEN COALESCE(direct_outflows.type, reversed_outflows.type) = ? THEN -entries.amount ELSE 0 END), 0) AS personal_withdrawal',
                [ManagementOutflowType::PersonalWithdrawal->value],
            )
            ->first(['operating_outflow', 'personal_withdrawal']);

        $totalBalance = LedgerEntry::query()
            ->join('wallets', 'wallets.id', '=', 'ledger_entries.wallet_id')
            ->whereNull('wallets.archived_at')
            ->sum('ledger_entries.amount');

        $distributionTotal = DB::table('financial_transactions as transactions')
            ->leftJoin('management_distributions as direct_distributions', 'direct_distributions.financial_transaction_id', '=', 'transactions.id')
            ->leftJoin('management_distributions as reversed_distributions', 'reversed_distributions.financial_transaction_id', '=', 'transactions.reversal_of_id')
            ->whereBetween('transactions.effective_date', [$filters['from'], $filters['to']])
            ->where(fn ($query) => $query->whereNotNull('direct_distributions.id')->orWhereNotNull('reversed_distributions.id'))
            ->selectRaw('COALESCE(SUM(CASE WHEN direct_distributions.id IS NOT NULL THEN direct_distributions.amount ELSE -reversed_distributions.amount END), 0) AS amount')
            ->value('amount');

        return [
            'wallets' => $wallets,
            'transactions' => $transactions,
            'summary' => [
                'total_balance' => (int) $totalBalance,
                'period_income' => (int) $periodTotals->income,
                'period_expense' => (int) $periodTotals->expense,
                'period_operating_outflow' => (int) $outflowTotals->operating_outflow,
                'period_personal_withdrawal' => (int) $outflowTotals->personal_withdrawal,
                'period_distribution' => (int) $distributionTotal,
            ],
        ];
    }

    /** @return array{transaction_id: ?int, wallet_id: ?int, type: ?string, category_id: ?int, from: string, to: string} */
    public static function defaultFilters(): array
    {
        $today = CarbonImmutable::today();

        return ['transaction_id' => null, 'wallet_id' => null, 'type' => null, 'category_id' => null, 'from' => $today->startOfMonth()->toDateString(), 'to' => $today->toDateString()];
    }
}
