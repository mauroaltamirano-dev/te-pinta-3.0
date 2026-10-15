<?php

namespace App\Modules\Products\Pricing;

use InvalidArgumentException;

/** H remains the only stored tariff. R=(2H-D)/2, including legacy half-peso surcharges. */
final class HomogeneousHalfDozenPrice
{
    public static function surcharge(int $half, int $dozen): string
    {
        $twice = 2 * $half - $dozen;
        $absolute = abs($twice);

        return ($twice < 0 ? '-' : '').intdiv($absolute, 2).($absolute % 2 === 0 ? '' : '.5');
    }

    public static function fromSurcharge(int $dozen, string $surcharge): int
    {
        if ($dozen < 0 || $dozen > 2147483647 || ! preg_match('/\A([0-9]{1,10})(?:\.(0|5))?\z/', $surcharge, $matches)) {
            throw new InvalidArgumentException('El recargo debe ser no negativo, en pesos o medios pesos.');
        }
        $twice = (int) $matches[1] * 2 + (($matches[2] ?? '0') === '5' ? 1 : 0);
        $halfTwice = $dozen + $twice;
        if ($halfTwice % 2 !== 0 || $halfTwice > 2147483647 * 2) {
            throw new InvalidArgumentException('La media resultante debe ser representable en pesos enteros sin redondear el precio configurado.');
        }

        return intdiv($halfTwice, 2);
    }
}
