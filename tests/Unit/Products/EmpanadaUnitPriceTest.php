<?php

namespace Tests\Unit\Products;

use App\Modules\Products\Pricing\EmpanadaUnitPrice;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class EmpanadaUnitPriceTest extends TestCase
{
    /** @return array<string, array{int, int, int}> */
    public static function requiredExamples(): array
    {
        return [
            'dulce 20000' => [20000, 10, 1800],
            'jamón y queso 22000' => [22000, 10, 2000],
            'salteña 18000 (1650 rounds half up)' => [18000, 10, 1700],
            'raw dulce 20000 - 2000' => [18000, 10, 1700],
        ];
    }

    #[DataProvider('requiredExamples')]
    public function test_unit_price_is_the_dozen_over_twelve_plus_the_surcharge_rounded_to_hundreds(int $dozen, int $percent, int $unit): void
    {
        self::assertSame($unit, EmpanadaUnitPrice::fromDozen($dozen, $percent));
    }

    public function test_rounding_is_half_up_to_the_nearest_hundred(): void
    {
        // 12000 → 1100 exactly; 12546 → 1150.05 → 1200; 12545 → 1149.96 → 1100; 18000 → 1650 exactly → 1700.
        self::assertSame(1100, EmpanadaUnitPrice::fromDozen(12000, 10));
        self::assertSame(1200, EmpanadaUnitPrice::fromDozen(12546, 10));
        self::assertSame(1100, EmpanadaUnitPrice::fromDozen(12545, 10));
        self::assertSame(1700, EmpanadaUnitPrice::fromDozen(18000, 10));
        self::assertSame(1700, EmpanadaUnitPrice::fromDozen(20000, 0));
        self::assertSame(0, EmpanadaUnitPrice::fromDozen(0, 10));
    }

    public function test_out_of_range_inputs_are_rejected(): void
    {
        foreach ([[-1, 10], [20000, -1], [20000, 101]] as [$dozen, $percent]) {
            try {
                EmpanadaUnitPrice::fromDozen($dozen, $percent);
                self::fail("Accepted dozen {$dozen} with {$percent}%.");
            } catch (InvalidArgumentException) {
                $this->addToAssertionCount(1);
            }
        }
    }
}
