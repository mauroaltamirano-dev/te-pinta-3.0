<?php

namespace App\Modules\Orders\Enums;

enum TableRoundKind: string
{
    case Order = 'order';
    case Void = 'void';
}
