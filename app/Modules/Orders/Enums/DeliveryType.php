<?php

namespace App\Modules\Orders\Enums;

enum DeliveryType: string
{
    case DineIn = 'dine_in';
    case Delivery = 'delivery';
    case Pickup = 'pickup';
}
