<?php

namespace App\Modules\Orders\Support;

use Carbon\CarbonImmutable;

final class OrderBusinessDate
{
    public function today(): CarbonImmutable
    {
        return CarbonImmutable::now((string) config('orders.timezone'));
    }

    public function todayString(): string
    {
        return $this->today()->toDateString();
    }
}
