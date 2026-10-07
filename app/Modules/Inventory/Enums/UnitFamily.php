<?php

namespace App\Modules\Inventory\Enums;

enum UnitFamily: string
{
    case Weight = 'weight';
    case Volume = 'volume';
    case Count = 'count';
}
