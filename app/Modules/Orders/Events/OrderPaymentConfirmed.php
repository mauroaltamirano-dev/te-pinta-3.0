<?php

namespace App\Modules\Orders\Events;

use Carbon\CarbonImmutable;

final readonly class OrderPaymentConfirmed
{
    public function __construct(
        public int $orderId,
        public int $amount,
        public CarbonImmutable $confirmedOn,
        public int $adminId,
    ) {}
}
