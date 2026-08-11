<?php

namespace App\Modules\Dashboard\Contracts;

interface PublicPromotionFeed
{
    /** @return iterable<object> */
    public function activeItems(): iterable;
}
