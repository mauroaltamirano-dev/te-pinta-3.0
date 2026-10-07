<?php

namespace App\Modules\Inventory\Enums;

enum InventorySourceType: string
{
    case Purchase = 'purchase';
    case Production = 'production';
    case Order = 'order';
    case Adjustment = 'adjustment';
    case Reversal = 'reversal';
}
