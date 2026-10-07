<?php

namespace App\Modules\Inventory\Enums;

enum InventoryTransactionType: string
{
    case PurchaseReceipt = 'purchase_receipt';
    case Adjustment = 'adjustment';
    case Production = 'production';
    case ProductionConsumption = 'production_consumption';
    case SaleConsumption = 'sale_consumption';
    case Reversal = 'reversal';
}
