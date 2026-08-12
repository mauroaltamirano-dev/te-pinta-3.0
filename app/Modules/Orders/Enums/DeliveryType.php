<?php

namespace App\Modules\Orders\Enums;

enum DeliveryType: string
{
    case Delivery = 'delivery';
    case Pickup = 'pickup';
}
