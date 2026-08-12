<?php

namespace App\Modules\Orders\Enums;

enum DeliveryPeriod: string
{
    case Midday = 'midday';
    case Afternoon = 'afternoon';
    case Night = 'night';
}
