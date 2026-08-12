<?php

namespace App\Modules\Orders\Enums;

enum OrderSource: string
{
    case Admin = 'admin';
    case PublicCheckout = 'public_checkout';
}
