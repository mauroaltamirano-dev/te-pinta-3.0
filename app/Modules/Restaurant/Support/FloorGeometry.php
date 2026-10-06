<?php

namespace App\Modules\Restaurant\Support;

final class FloorGeometry
{
    public const SCALE = 10000;

    public static function units(string|int|float $value): int
    {
        // Only validated decimal input reaches here; no floating-point arithmetic.
        [$whole, $fraction] = array_pad(explode('.', (string) $value, 2), 2, '');

        return ((int) $whole * self::SCALE) + (int) str_pad($fraction, 4, '0');
    }

    public static function decimal(string|int|float $value): string
    {
        $units = self::units($value);

        return sprintf('%d.%04d', intdiv($units, self::SCALE), $units % self::SCALE);
    }

    /**
     * @param  array<string, mixed>  $element
     * @return array<string, string>
     */
    public static function errors(array $element, int $areaWidth, int $areaHeight, bool $obstacle = false): array
    {
        $xKey = $obstacle ? 'x' : 'position_x';
        $yKey = $obstacle ? 'y' : 'position_y';
        $x = self::units($element[$xKey]);
        $y = self::units($element[$yKey]);
        $w = self::units($element['width']);
        $h = self::units($element['height']);
        $shape = $element[$obstacle ? 'type' : 'shape'];
        $angle = $shape === 'circle' ? 0 : (int) ($element['rotation'] ?? 0) % 180;
        // ceil(sqrt(1/2) * 10000) conservatively contains the rotated footprint.
        [$cos, $sin] = match ($angle) {
            0 => [10000, 0],
            90 => [0, 10000],
            default => [7072, 7072],
        };
        $errors = [];
        if ($w <= 0) {
            $errors['width'] = 'La dimensión debe ser positiva.';
        }
        if ($h <= 0) {
            $errors['height'] = 'La dimensión debe ser positiva.';
        }
        if ($shape === 'circle' && abs($w * $areaWidth - $h * $areaHeight) > self::SCALE) {
            $errors['height'] = 'Un círculo debe conservar dimensiones físicas iguales.';
        }
        $extentX = $cos * $w * $areaWidth + $sin * $h * $areaHeight;
        $extentY = $sin * $w * $areaWidth + $cos * $h * $areaHeight;
        $centerX = (2 * $x + $w) * self::SCALE * $areaWidth;
        $centerY = (2 * $y + $h) * self::SCALE * $areaHeight;
        if ($x + $w > self::SCALE || $centerX < $extentX || $centerX + $extentX > 2 * self::SCALE * self::SCALE * $areaWidth) {
            $errors[$xKey] = 'El elemento rotado debe quedar dentro del área.';
        }
        if ($y + $h > self::SCALE || $centerY < $extentY || $centerY + $extentY > 2 * self::SCALE * self::SCALE * $areaHeight) {
            $errors[$yKey] = 'El elemento rotado debe quedar dentro del área.';
        }

        return $errors;
    }
}
