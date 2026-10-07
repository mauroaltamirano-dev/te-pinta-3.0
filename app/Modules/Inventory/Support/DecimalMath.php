<?php

namespace App\Modules\Inventory\Support;

use InvalidArgumentException;

final class DecimalMath
{
    public static function compare(string $left, string $right): int
    {
        [$leftSign, $leftDigits, $leftScale] = self::parse($left);
        [$rightSign, $rightDigits, $rightScale] = self::parse($right);
        if (trim($leftDigits, '0') === '' && trim($rightDigits, '0') === '') {
            return 0;
        }
        if ($leftSign !== $rightSign) {
            return $leftSign <=> $rightSign;
        }

        $scale = max($leftScale, $rightScale);
        $comparison = self::compareUnsigned(
            $leftDigits.str_repeat('0', $scale - $leftScale),
            $rightDigits.str_repeat('0', $scale - $rightScale),
        );

        return $leftSign < 0 ? -$comparison : $comparison;
    }

    public static function subtract(string $left, string $right): string
    {
        [$leftSign, $leftDigits, $leftScale] = self::parse($left);
        [$rightSign, $rightDigits, $rightScale] = self::parse($right);
        $scale = max($leftScale, $rightScale);
        $leftDigits .= str_repeat('0', $scale - $leftScale);
        $rightDigits .= str_repeat('0', $scale - $rightScale);

        if ($leftSign !== $rightSign) {
            return self::format($leftSign, self::addUnsigned($leftDigits, $rightDigits), $scale);
        }

        $comparison = self::compareUnsigned($leftDigits, $rightDigits);
        if ($comparison === 0) {
            return self::format(1, '0', $scale);
        }

        return $comparison > 0
            ? self::format($leftSign, self::subtractUnsigned($leftDigits, $rightDigits), $scale)
            : self::format(-$leftSign, self::subtractUnsigned($rightDigits, $leftDigits), $scale);
    }

    /**
     * Returns round(amount * numerator / denominator), using NUMERIC-compatible
     * half-up rounding without converting decimal quantities to floats.
     */
    public static function roundIntegerTimesRatio(int $amount, string $numerator, string $denominator): int
    {
        [$numeratorSign, $numeratorDigits, $numeratorScale] = self::parse($numerator);
        [$denominatorSign, $denominatorDigits, $denominatorScale] = self::parse($denominator);
        if (trim($denominatorDigits, '0') === '') {
            throw new InvalidArgumentException('Cost quantity must be positive.');
        }

        $sign = ($amount < 0 ? -1 : 1) * $numeratorSign * $denominatorSign;
        $dividend = self::multiplyUnsigned((string) abs($amount), $numeratorDigits).str_repeat('0', $denominatorScale);
        $divisor = $denominatorDigits.str_repeat('0', $numeratorScale);
        [$quotient, $remainder] = self::divideUnsigned($dividend, $divisor);
        if (self::compareUnsigned(self::multiplyUnsigned($remainder, '2'), $divisor) >= 0) {
            $quotient = self::addOne($quotient);
        }
        if (self::compareUnsigned($quotient, (string) PHP_INT_MAX) > 0) {
            throw new InvalidArgumentException('Rounded cost exceeds the supported integer range.');
        }

        return $sign * (int) $quotient;
    }

    /**
     * Splits an integer cost across positive decimal quantities without losing
     * or creating cents. Equal fractional remainders retain input order.
     *
     * @param  array<int, string>  $quantities
     * @return list<int>
     */
    public static function allocateIntegerByRatios(int $amount, array $quantities): array
    {
        if ($amount < 0 || $quantities === []) {
            throw new InvalidArgumentException('Cost allocation expects a non-negative amount and quantities.');
        }

        $scale = 0;
        $parsed = [];
        foreach ($quantities as $quantity) {
            [$sign, $digits, $quantityScale] = self::parse($quantity);
            if ($sign < 0 || trim($digits, '0') === '') {
                throw new InvalidArgumentException('Cost allocation quantities must be positive.');
            }
            $scale = max($scale, $quantityScale);
            $parsed[] = [$digits, $quantityScale];
        }

        $numerators = [];
        $denominator = '0';
        foreach ($parsed as [$digits, $quantityScale]) {
            $numerator = $digits.str_repeat('0', $scale - $quantityScale);
            $numerators[] = $numerator;
            $denominator = self::addUnsigned($denominator, $numerator);
        }
        if ($denominator === '0') {
            throw new InvalidArgumentException('Cost allocation quantities must have a positive sum.');
        }

        $allocations = [];
        $remainders = [];
        $allocated = 0;
        foreach ($numerators as $index => $numerator) {
            [$share, $remainder] = self::divideUnsigned(self::multiplyUnsigned((string) $amount, $numerator), $denominator);
            if (self::compareUnsigned($share, (string) PHP_INT_MAX) > 0) {
                throw new InvalidArgumentException('Cost allocation exceeds the supported integer range.');
            }
            $allocations[$index] = (int) $share;
            $remainders[$index] = $remainder;
            $allocated += $allocations[$index];
        }

        $remainderOrder = array_keys($allocations);
        usort($remainderOrder, static fn (int $left, int $right): int => self::compareUnsigned($remainders[$right], $remainders[$left]) ?: $left <=> $right);
        for ($cent = $allocated; $cent < $amount; $cent++) {
            $allocations[$remainderOrder[($cent - $allocated) % count($remainderOrder)]]++;
        }

        return array_values($allocations);
    }

    public static function multiply(string $left, string $right, int $outputScale): string
    {
        [$leftSign, $leftDigits, $leftScale] = self::parse($left);
        [$rightSign, $rightDigits, $rightScale] = self::parse($right);
        $digits = self::multiplyUnsigned($leftDigits, $rightDigits);
        $scale = $leftScale + $rightScale;

        if ($scale > $outputScale) {
            $discarded = substr($digits, -($scale - $outputScale));
            if (trim($discarded, '0') !== '') {
                throw new InvalidArgumentException('Decimal result cannot be represented at the requested precision.');
            }
            $digits = substr($digits, 0, max(1, strlen($digits) - ($scale - $outputScale)));
        } elseif ($scale < $outputScale) {
            $digits .= str_repeat('0', $outputScale - $scale);
        }

        return self::format($leftSign * $rightSign, $digits, $outputScale);
    }

    public static function normalize(string $value, int $outputScale): string
    {
        [$sign, $digits, $scale] = self::parse($value);

        if ($scale > $outputScale) {
            $discarded = substr($digits, -($scale - $outputScale));
            if (trim($discarded, '0') !== '') {
                throw new InvalidArgumentException('Decimal value exceeds the requested precision.');
            }
            $digits = substr($digits, 0, max(1, strlen($digits) - ($scale - $outputScale)));
        } elseif ($scale < $outputScale) {
            $digits .= str_repeat('0', $outputScale - $scale);
        }

        return self::format($sign, $digits, $outputScale);
    }

    public static function divideIntegerByDecimal(int $numerator, string $denominator, int $outputScale): string
    {
        if ($numerator < 0 || $outputScale < 0) {
            throw new InvalidArgumentException('Cost division expects non-negative values and scale.');
        }

        [$sign, $denominatorDigits, $denominatorScale] = self::parse($denominator);
        if ($sign < 0 || trim($denominatorDigits, '0') === '') {
            throw new InvalidArgumentException('Cost quantity must be positive.');
        }

        $scaledNumerator = (string) $numerator.str_repeat('0', $denominatorScale + $outputScale);
        [$quotient, $remainder] = self::divideUnsigned($scaledNumerator, $denominatorDigits);
        if (self::compareUnsigned(self::multiplyUnsigned($remainder, '2'), $denominatorDigits) >= 0) {
            $quotient = self::addOne($quotient);
        }

        return self::format(1, $quotient, $outputScale);
    }

    /** @return array{int, string, int} */
    private static function parse(string $value): array
    {
        $value = trim($value);
        if (! preg_match('/^([+-]?)(\d+)(?:\.(\d+))?$/', $value, $matches)) {
            throw new InvalidArgumentException('Invalid decimal value.');
        }

        $fraction = $matches[3] ?? '';
        $digits = ltrim($matches[2].$fraction, '0');

        return [$matches[1] === '-' ? -1 : 1, $digits === '' ? '0' : $digits, strlen($fraction)];
    }

    private static function format(int $sign, string $digits, int $scale): string
    {
        $digits = ltrim($digits, '0');
        $digits = $digits === '' ? '0' : $digits;
        if ($scale > 0) {
            $digits = str_pad($digits, $scale + 1, '0', STR_PAD_LEFT);
            $digits = substr($digits, 0, -$scale).'.'.substr($digits, -$scale);
        }

        return $sign < 0 && trim(str_replace('.', '', $digits), '0') !== '' ? '-'.$digits : $digits;
    }

    private static function multiplyUnsigned(string $left, string $right): string
    {
        if ($left === '0' || $right === '0') {
            return '0';
        }

        $left = strrev($left);
        $right = strrev($right);
        $result = array_fill(0, strlen($left) + strlen($right) + 1, 0);
        for ($i = 0; $i < strlen($left); $i++) {
            for ($j = 0; $j < strlen($right); $j++) {
                $result[$i + $j] += (int) $left[$i] * (int) $right[$j];
            }
        }
        for ($i = 0; $i < count($result) - 1; $i++) {
            $result[$i + 1] += intdiv($result[$i], 10);
            $result[$i] %= 10;
        }

        return ltrim(implode('', array_reverse($result)), '0') ?: '0';
    }

    /** @return array{string, string} */
    private static function divideUnsigned(string $dividend, string $divisor): array
    {
        $divisor = ltrim($divisor, '0');
        $remainder = '0';
        $quotient = '';
        foreach (str_split(ltrim($dividend, '0') ?: '0') as $digit) {
            $remainder = ltrim(($remainder === '0' ? '' : $remainder).$digit, '0') ?: '0';
            $quotientDigit = 0;
            while (self::compareUnsigned($remainder, $divisor) >= 0) {
                $remainder = self::subtractUnsigned($remainder, $divisor);
                $quotientDigit++;
            }
            $quotient .= (string) $quotientDigit;
        }

        return [ltrim($quotient, '0') ?: '0', $remainder];
    }

    private static function compareUnsigned(string $left, string $right): int
    {
        $left = ltrim($left, '0') ?: '0';
        $right = ltrim($right, '0') ?: '0';

        return strlen($left) <=> strlen($right) ?: strcmp($left, $right);
    }

    private static function subtractUnsigned(string $left, string $right): string
    {
        $result = '';
        $borrow = 0;
        $right = str_pad($right, strlen($left), '0', STR_PAD_LEFT);
        for ($i = strlen($left) - 1; $i >= 0; $i--) {
            $digit = (int) $left[$i] - (int) $right[$i] - $borrow;
            if ($digit < 0) {
                $digit += 10;
                $borrow = 1;
            } else {
                $borrow = 0;
            }
            $result = $digit.$result;
        }

        return ltrim($result, '0') ?: '0';
    }

    private static function addUnsigned(string $left, string $right): string
    {
        $carry = 0;
        $result = '';
        $length = max(strlen($left), strlen($right));
        $left = str_pad($left, $length, '0', STR_PAD_LEFT);
        $right = str_pad($right, $length, '0', STR_PAD_LEFT);
        for ($i = $length - 1; $i >= 0; $i--) {
            $sum = (int) $left[$i] + (int) $right[$i] + $carry;
            $result = ($sum % 10).$result;
            $carry = intdiv($sum, 10);
        }

        return $carry === 1 ? '1'.$result : $result;
    }

    private static function addOne(string $value): string
    {
        $carry = 1;
        for ($i = strlen($value) - 1; $i >= 0 && $carry === 1; $i--) {
            $digit = (int) $value[$i] + 1;
            $value[$i] = (string) ($digit % 10);
            $carry = intdiv($digit, 10);
        }

        return $carry === 1 ? '1'.$value : $value;
    }
}
