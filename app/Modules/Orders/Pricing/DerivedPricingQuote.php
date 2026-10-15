<?php

namespace App\Modules\Orders\Pricing;

/**
 * @phpstan-type Line array{mode: string, units: int, composition: array<int, int>, exact_numerator_12: int, line_total: int}
 */
final readonly class DerivedPricingQuote
{
    /** @param list<Line> $lines */
    public function __construct(
        public int $productsNumerator12,
        public int $unitsBaseline,
        public array $lines,
        public int $productsSubtotal,
        public int $total,
        public int $roundingAdjustmentNumerator1200,
    ) {}
}
