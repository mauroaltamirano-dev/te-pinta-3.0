<?php

namespace App\Modules\Analytics\Contracts;

interface AnalyticsReader
{
    /** @return array<string, int|float|string> */
    public function summary(): array;
}
