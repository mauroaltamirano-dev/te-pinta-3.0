<?php

namespace App\Modules\Orders\Enums;

enum DeliverySlot: string
{
    case Midday = 'midday';
    case Afternoon = 'afternoon';
    case Night = 'night';
}
