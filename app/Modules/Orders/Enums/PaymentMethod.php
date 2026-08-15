<?php

namespace App\Modules\Orders\Enums;

enum PaymentMethod: string
{
    case Cash = 'cash';
    case BankTransfer = 'bank_transfer';
    case DebitCard = 'debit_card';
}
