<?php

namespace App\Modules\Management\Enums;

enum PurchasePriceMode: string
{
    case Total = 'total';
    case Unit = 'unit';
}
