<?php

namespace App\Modules\Management\Enums;

enum ManagementOutflowType: string
{
    case Purchase = 'purchase';
    case Expense = 'expense';
    case PersonalWithdrawal = 'personal_withdrawal';

    public function label(): string
    {
        return match ($this) {
            self::Purchase => 'Compra',
            self::Expense => 'Gasto',
            self::PersonalWithdrawal => 'Retiro personal',
        };
    }

    public function isOperating(): bool
    {
        return $this !== self::PersonalWithdrawal;
    }
}
