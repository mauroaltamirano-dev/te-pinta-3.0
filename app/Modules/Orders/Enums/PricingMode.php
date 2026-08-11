<?php

namespace App\Modules\Orders\Enums;

enum PricingMode: string
{
    case Unit = 'unit';
    case HalfDozen = 'half_dozen';
    case Dozen = 'dozen';
}
