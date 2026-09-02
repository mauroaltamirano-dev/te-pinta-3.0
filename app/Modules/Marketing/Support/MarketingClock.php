<?php

namespace App\Modules\Marketing\Support;

use Carbon\CarbonImmutable;

final class MarketingClock
{
    public function timezone(): string
    {
        return (string) config('orders.timezone');
    }

    public function now(): CarbonImmutable
    {
        return CarbonImmutable::now($this->timezone())->utc();
    }

    public function parseLocal(string $value): CarbonImmutable
    {
        return CarbonImmutable::createFromFormat('!Y-m-d\TH:i', $value, $this->timezone())->utc();
    }

    public function formatLocal(CarbonImmutable $value): string
    {
        return $value->setTimezone($this->timezone())->format('Y-m-d\TH:i');
    }
}
