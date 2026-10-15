<?php

namespace App\Modules\Orders\Pricing;

/** Exact allocation of physical empanadas; counts are pieces, never packages. */
final readonly class DerivedTierAllocation
{
    /** @param array<int, array{dozen_units: int, half_dozen_units: int, unit_units: int}> $allocations */
    public function __construct(
        public int $exactSubtotalNumerator,
        public array $allocations,
    ) {}
}
