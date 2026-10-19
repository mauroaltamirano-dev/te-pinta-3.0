<?php

namespace App\Modules\Products\Pricing;

use InvalidArgumentException;

/** U = D / 12 × (1 + surcharge%), rounded half-up to ARS 100. Stored on the product, never computed per order. */
final class EmpanadaUnitPrice
{
    public const ROUNDING_STEP = 100;

    /** The empanada half dozen is never entered: it is six units at the stored unit price. */
    public static function halfDozen(int $unitPrice): int
    {
        return 6 * $unitPrice;
    }

    public static function fromDozen(int $dozenPrice, int $surchargePercent): int
    {
        if ($dozenPrice < 0 || $surchargePercent < 0 || $surchargePercent > 100) {
            throw new InvalidArgumentException('The dozen price must be non-negative and the surcharge between 0 and 100.');
        }
        // D × (100 + p) / 1200 pesos, expressed in ROUNDING_STEP units with half-up rounding.
        $step = 1200 * self::ROUNDING_STEP;

        return intdiv($dozenPrice * (100 + $surchargePercent) + intdiv($step, 2), $step) * self::ROUNDING_STEP;
    }
}
