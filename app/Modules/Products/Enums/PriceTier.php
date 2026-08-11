<?php

namespace App\Modules\Products\Enums;

enum PriceTier: string
{
    case Unit = 'unit';
    case HalfDozen = 'half_dozen';
    case Dozen = 'dozen';
}
