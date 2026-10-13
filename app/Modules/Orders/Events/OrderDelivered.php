<?php

namespace App\Modules\Orders\Events;

use Carbon\CarbonImmutable;

final readonly class OrderDelivered
{
    public function __construct(public int $orderId, public CarbonImmutable $businessDate, public int $adminId) {}
}
