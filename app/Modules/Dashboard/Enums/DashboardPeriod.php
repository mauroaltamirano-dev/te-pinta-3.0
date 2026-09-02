<?php

namespace App\Modules\Dashboard\Enums;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

enum DashboardPeriod: string
{
    case Today = 'today';
    case Week = 'week';
    case Month = 'month';
    case All = 'all';

    /** @return array{0: CarbonImmutable|null, 1: CarbonImmutable|null} */
    public function range(CarbonImmutable $today): array
    {
        return match ($this) {
            self::Today => [$today->startOfDay(), $today->startOfDay()],
            self::Week => [
                $today->startOfWeek(CarbonInterface::MONDAY)->startOfDay(),
                $today->endOfWeek(CarbonInterface::SUNDAY)->startOfDay(),
            ],
            self::Month => [
                $today->startOfMonth()->startOfDay(),
                $today->endOfMonth()->startOfDay(),
            ],
            self::All => [null, null],
        };
    }
}
