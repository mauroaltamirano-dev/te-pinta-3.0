<?php

namespace App\Modules\Management\Queries;

use Illuminate\Support\Facades\DB;

final class BuildManagementFinancialSummary
{
    /** @return array<string, int> */
    public function execute(string $from, string $to): array
    {
        // Aggregate each financial header once before joining unique semantic sources.
        // A purchase and its FinancialTransaction are not independent money movements.
        $amounts = DB::table('financial_transactions as transactions')
            ->join('ledger_entries as entries', 'entries.financial_transaction_id', '=', 'transactions.id')
            ->select('transactions.id', 'transactions.type', 'transactions.reversal_of_id', 'transactions.effective_date')
            ->selectRaw('SUM(entries.amount) AS amount')->groupBy('transactions.id');
        $query = DB::query()->fromSub($amounts, 'amounts')
            ->leftJoin('financial_transactions as originals', 'originals.id', '=', 'amounts.reversal_of_id')
            ->leftJoin('management_outflows as direct_outflows', 'direct_outflows.financial_transaction_id', '=', 'amounts.id')
            ->leftJoin('management_outflows as reversed_outflows', 'reversed_outflows.financial_transaction_id', '=', 'amounts.reversal_of_id')
            ->leftJoin('purchases as direct_purchases', 'direct_purchases.financial_transaction_id', '=', 'amounts.id')
            ->leftJoin('purchases as reversed_purchases', 'reversed_purchases.financial_transaction_id', '=', 'amounts.reversal_of_id')
            ->leftJoin('management_order_payments as direct_payments', 'direct_payments.financial_transaction_id', '=', 'amounts.id')
            ->leftJoin('management_order_payments as reversed_payments', 'reversed_payments.financial_transaction_id', '=', 'amounts.reversal_of_id')
            ->leftJoin('management_distributions as direct_distributions', 'direct_distributions.financial_transaction_id', '=', 'amounts.id')
            ->leftJoin('management_distributions as reversed_distributions', 'reversed_distributions.financial_transaction_id', '=', 'amounts.reversal_of_id')
            ->selectRaw('COALESCE(SUM(amounts.amount), 0) AS total_balance')
            ->selectRaw('COALESCE(SUM(amounts.amount) FILTER (WHERE amounts.effective_date < ?), 0) AS period_opening_balance', [$from])
            ->selectRaw('COALESCE(SUM(amounts.amount) FILTER (WHERE amounts.effective_date <= ?), 0) AS period_closing_balance', [$to]);

        $expressions = [
            'period_income' => "CASE WHEN COALESCE(originals.type, amounts.type) = 'manual_income' OR direct_payments.id IS NOT NULL OR reversed_payments.id IS NOT NULL THEN amounts.amount ELSE 0 END",
            'period_expense' => "CASE WHEN COALESCE(originals.type, amounts.type) = 'manual_expense' THEN -amounts.amount ELSE 0 END",
            'period_purchases' => "CASE WHEN COALESCE(direct_outflows.type, reversed_outflows.type) = 'purchase' OR direct_purchases.id IS NOT NULL OR reversed_purchases.id IS NOT NULL THEN -amounts.amount ELSE 0 END",
            'period_expenses' => "CASE WHEN COALESCE(direct_outflows.type, reversed_outflows.type) = 'expense' THEN -amounts.amount ELSE 0 END",
            'period_personal_withdrawal' => "CASE WHEN COALESCE(direct_outflows.type, reversed_outflows.type) = 'personal_withdrawal' THEN -amounts.amount ELSE 0 END",
            'period_other_movements' => "CASE WHEN COALESCE(originals.type, amounts.type) IN ('opening', 'adjustment_increase', 'adjustment_decrease') THEN amounts.amount ELSE 0 END",
            'period_distribution_applied' => 'COALESCE(direct_distributions.amount, 0)',
            'period_distribution_reversed' => 'COALESCE(reversed_distributions.amount, 0)',
            'period_purchase_shipping' => 'COALESCE(direct_purchases.shipping_amount, 0) - COALESCE(reversed_purchases.shipping_amount, 0)',
        ];
        foreach ($expressions as $name => $expression) {
            $query->selectRaw("COALESCE(SUM($expression) FILTER (WHERE amounts.effective_date BETWEEN ? AND ?), 0) AS $name", [$from, $to]);
        }
        $row = $query->first();
        $summary = array_map(static fn ($value): int => (int) $value, get_object_vars($row));
        $summary['period_operating_outflow'] = $summary['period_purchases'] + $summary['period_expenses'];
        $summary['period_operating_result'] = $summary['period_income'] - $summary['period_operating_outflow'] - $summary['period_expense'];
        $summary['period_distribution'] = $summary['period_distribution_applied'] - $summary['period_distribution_reversed'];
        $summary['period_purchase_subtotal'] = $summary['period_purchases'] - $summary['period_purchase_shipping'];
        $summary['period_balance_change'] = $summary['period_closing_balance'] - $summary['period_opening_balance'];
        $summary['reconciliation_difference'] = $summary['period_balance_change'] - ($summary['period_operating_result'] - $summary['period_personal_withdrawal'] + $summary['period_other_movements']);

        return $summary;
    }
}
