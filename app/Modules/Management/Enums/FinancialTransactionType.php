<?php

namespace App\Modules\Management\Enums;

enum FinancialTransactionType: string
{
    case Opening = 'opening';
    case AdjustmentIncrease = 'adjustment_increase';
    case AdjustmentDecrease = 'adjustment_decrease';
    case ManualIncome = 'manual_income';
    case ManualExpense = 'manual_expense';
    case ManagementOutflow = 'management_outflow';
    case Transfer = 'transfer';
    case Distribution = 'distribution';
    case OrderPayment = 'order_payment';
    case Purchase = 'purchase';
    case Reversal = 'reversal';

    public function signedAmount(int $amount): int
    {
        return match ($this) {
            self::Opening, self::AdjustmentIncrease, self::ManualIncome, self::OrderPayment => $amount,
            self::AdjustmentDecrease, self::ManualExpense => -$amount,
            self::ManagementOutflow => throw new \LogicException('Management outflows determine their ledger sign in their use case.'),
            self::Transfer => throw new \LogicException('Transfers determine both ledger impacts in their use case.'),
            self::Distribution => throw new \LogicException('Distributions determine their ledger allocations in their use case.'),
            self::Purchase => throw new \LogicException('Purchases determine their ledger impact in their use case.'),
            self::Reversal => throw new \LogicException('Reversals copy opposite ledger entries.'),
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Opening => 'Apertura',
            self::AdjustmentIncrease => 'Ajuste positivo',
            self::AdjustmentDecrease => 'Ajuste negativo',
            self::ManualIncome => 'Ingreso manual',
            self::ManualExpense => 'Egreso manual',
            self::ManagementOutflow => 'Egreso de gestión',
            self::Transfer => 'Transferencia',
            self::Distribution => 'Distribución',
            self::OrderPayment => 'Pago de pedido',
            self::Purchase => 'Compra',
            self::Reversal => 'Reversión',
        };
    }

    /** @return list<self> */
    public static function manualCases(): array
    {
        return [self::Opening, self::AdjustmentIncrease, self::AdjustmentDecrease, self::ManualIncome, self::ManualExpense];
    }
}
