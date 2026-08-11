<?php

namespace App\Modules\Orders\Enums;

enum OrderStatus: string
{
    case Waiting = 'waiting';
    case Prepared = 'prepared';
    case Delivered = 'delivered';
}
