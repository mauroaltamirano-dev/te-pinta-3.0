<?php

namespace Tests\Unit\Orders;

use App\Modules\Orders\Enums\DiscountType;
use App\Modules\Orders\Pricing\ExactOrderTotals;
use PHPUnit\Framework\TestCase;

final class OrderTotalRoundingTest extends TestCase
{
    public function test_exact_totals_round_to_the_nearest_hundred(): void
    {
        self::assertSame(23400, ExactOrderTotals::calculate(23350 * 12, 0, 0)->total);
        self::assertSame(49800, ExactOrderTotals::calculate(49750 * 12, 0, 0)->total);
        self::assertSame(21900, ExactOrderTotals::calculate(21900 * 12, 0, 0)->total);
    }

    public function test_final_total_rounds_once_half_up_to_hundreds(): void
    {
        self::assertSame(200, ExactOrderTotals::calculate(150 * 12, 0, 0)->total);
        self::assertSame(100, ExactOrderTotals::calculate(149 * 12, 0, 0)->total);
        self::assertSame(100, ExactOrderTotals::calculate(1206, 0, 0)->total);
        self::assertSame(0, ExactOrderTotals::calculate(49 * 12, 0, 0)->total);
    }

    public function test_rounding_applies_after_addons_discount_and_shipping_not_per_component(): void
    {
        // Each component alone would round differently; only the final net is rounded.
        $totals = ExactOrderTotals::calculate(142000, 123, 987, DiscountType::Percentage, 10);

        self::assertSame(11700, $totals->total);
        self::assertSame($totals->total * 1200, $totals->netNumerator1200 + $totals->roundingAdjustmentNumerator1200);
        self::assertSame(1100, ExactOrderTotals::calculate(123 * 12, 0, 987)->total);
        self::assertSame(
            $totals->total,
            $totals->productsSubtotal + 123 - $totals->discountAmount + 987 + $totals->roundingAdjustment,
        );
    }
}
