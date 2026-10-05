<?php

namespace App\Modules\Orders\Enums;

enum OrderSource: string
{
    case Pos = 'pos';
    case Admin = 'admin';
    case PublicCheckout = 'public_checkout';
}
