<?php

namespace App\Modules\Marketing\Enums;

enum FeaturedProductStatus: string
{
    case Inactive = 'inactive';
    case Scheduled = 'scheduled';
    case Active = 'active';
    case Finished = 'finished';
}
