<?php

namespace App\Modules\Orders\Enums;

enum TableSessionStatus: string
{
    case Open = 'open';
    case Closed = 'closed';
    case Cancelled = 'cancelled';
}
