<?php

namespace App\Modules\Orders\Enums;

enum OrderCondition: string
{
    case Raw = 'raw';
    case Cooked = 'cooked';
}
