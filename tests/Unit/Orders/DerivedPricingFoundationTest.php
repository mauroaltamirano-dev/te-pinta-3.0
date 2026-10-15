<?php

namespace Tests\Unit\Orders;

use App\Modules\Orders\Enums\DiscountType;
use App\Modules\Orders\Pricing\DerivedPricingCalculator;
use App\Modules\Orders\Pricing\DerivedTierAllocator;
use App\Modules\Orders\Pricing\ExactOrderTotals;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class DerivedPricingFoundationTest extends TestCase
{
    /** Explicit commercial examples, not production catalog data. */
    private function product(int $id, int $quantity, int $unit = 2200, int $half = 13000, int $dozen = 22000, string $family = 'empanada'): array
    {
        return ['product_id' => $id, 'quantity' => $quantity, 'unit_price' => $unit, 'half_dozen_price' => $half, 'dozen_price' => $dozen, 'pricing_family' => $family, 'name' => ([1 => 'Matambre', 2 => 'Caprese', 3 => 'Salteña', 4 => 'Pollo', 5 => 'Humita'][$id] ?? 'Unit').' (fixture)'];
    }

    public function test_homogeneous_and_mixed_sixes_use_different_surcharges(): void
    {
        $calculator = new DerivedPricingCalculator;
        self::assertSame(13000, $calculator->quote([$this->product(1, 6)], 1500)->total);
        self::assertSame(10500, $calculator->quote([$this->product(2, 6, 1800, 10500, 18000)], 1500)->total);
        $quote = $calculator->quote([$this->product(1, 3), $this->product(2, 3, 1800, 10500, 18000)], 1500);
        self::assertSame(11500, $quote->total);
        self::assertSame('combined_half_dozen', $quote->lines[0]['mode']);
        self::assertSame(500, $quote->unitsBaseline - $quote->total);
    }

    public function test_required_mixed_baskets_use_flavor_dozen_rates(): void
    {
        $calculator = new DerivedPricingCalculator;
        $cases = [
            [[$this->product(1, 3), $this->product(3, 2, 2100, 12000, 20000), $this->product(2, 1, 1800, 10500, 18000)], 11833],
            [[$this->product(1, 2), $this->product(5, 2, 1800, 10500, 18000), $this->product(3, 2, 2100, 12000, 20000), $this->product(4, 2, 2000, 11500, 19000)], 15267],
            [[$this->product(1, 3), $this->product(3, 3, 2100, 12000, 20000), $this->product(2, 6, 1800, 10500, 18000)], 19500],
            [[$this->product(1, 12)], 22000],
            [[$this->product(1, 6), $this->product(2, 6, 1800, 10500, 18000)], 20000],
            [[$this->product(1, 12), $this->product(2, 6, 1800, 10500, 18000)], 32500],
            [[$this->product(1, 12), $this->product(2, 12, 1800, 10500, 18000)], 40000],
            [[$this->product(1, 8), $this->product(3, 8, 2100, 12000, 20000), $this->product(2, 8, 1800, 10500, 18000)], 40000],
        ];
        foreach ($cases as [$items, $expected]) {
            $quote = $calculator->quote($items, 1500);
            self::assertSame($expected, $quote->total);
            self::assertSame(array_sum(array_column($items, 'quantity')), array_sum(array_column($quote->lines, 'units')));
            self::assertSame($quote->productsSubtotal, array_sum(array_column($quote->lines, 'line_total')));
        }
    }

    public function test_unit_products_do_not_complete_groups_or_receive_surcharges(): void
    {
        $items = [$this->product(1, 5), $this->product(2, 1, 987, 987, 987, 'unit'), $this->product(3, 12, 123, 123, 123, 'unit')];
        $quote = (new DerivedPricingCalculator)->quote($items, 1500);
        self::assertSame(11000 + 987 + 12 * 123, $quote->total);
        self::assertSame(['unit', 'unit', 'unit'], array_column($quote->lines, 'mode'));
        self::assertSame([11000, 987, 1476], array_column($quote->lines, 'line_total'));
    }

    public function test_rounding_is_half_up_once_at_peso_boundary_not_hundreds(): void
    {
        $quote = (new DerivedPricingCalculator)->quote([$this->product(1, 3), $this->product(2, 2, 2100, 12000, 20000), $this->product(3, 1, 1800, 10500, 18000)], 1500);
        self::assertSame(142000, $quote->productsNumerator12);
        self::assertSame(11833, $quote->total);
        self::assertSame(-400, $quote->roundingAdjustmentNumerator1200);
        $totals = ExactOrderTotals::calculate(142000, 123, 987, DiscountType::Percentage, 10);
        self::assertSame(11748, $totals->total);
        self::assertSame($totals->total * 1200, $totals->netNumerator1200 + $totals->roundingAdjustmentNumerator1200);
    }

    public function test_half_up_tie_is_rounded_up_one_peso(): void
    {
        self::assertSame(101, ExactOrderTotals::calculate(1206, 0, 0)->total);
        self::assertSame(123 + 987, ExactOrderTotals::calculate(123 * 12, 0, 987)->total);
    }

    public function test_raw_effective_rates_are_not_discounted_twice(): void
    {
        $quote = (new DerivedPricingCalculator)->quote([$this->product(1, 3, 2000, 12000, 20000), $this->product(2, 3, 1600, 9500, 16000)], 1500);
        self::assertSame(10500, $quote->total);
    }

    public function test_invalid_tiers_are_reported_but_do_not_block_unrelated_units(): void
    {
        $calculator = new DerivedPricingCalculator;
        self::assertSame(6600, $calculator->quote([$this->product(1, 3, 2200, 13200, 26400)], 1500)->total);
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Matambre (fixture)');
        $calculator->quote([$this->product(1, 6, 2200, 13200, 26400)], 1500);
    }

    public function test_mixed_guarantee_is_per_flavor_not_just_total(): void
    {
        $this->expectException(InvalidArgumentException::class);
        (new DerivedPricingCalculator)->quote([$this->product(1, 3, 1400, 8000, 14000), $this->product(2, 3)], 1500);
    }

    public function test_valid_fractional_saving_must_survive_peso_rounding(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('rounding');
        (new DerivedPricingCalculator)->quote([$this->product(1, 3, 2000, 11999, 20999), $this->product(2, 3, 2000, 11999, 20999)], 1500);
    }

    public function test_optimizer_can_choose_a_mixed_six_over_an_expensive_homogeneous_six(): void
    {
        $items = [$this->product(1, 12, 3000, 17000, 24000), $this->product(2, 6, 3000, 17000, 24000)];
        $allocation = (new DerivedTierAllocator)->allocate($items, 1500);
        self::assertSame(37500 * 12, $allocation->exactSubtotalNumerator);
        self::assertGreaterThan(0, $allocation->allocations[1]['half_dozen_units']);
        self::assertGreaterThan(0, $allocation->allocations[2]['half_dozen_units']);
    }

    public function test_optimizer_can_choose_a_cheap_homogeneous_six_over_a_mixed_six(): void
    {
        $items = [$this->product(1, 12, 3000, 12500, 24000), $this->product(2, 6, 3000, 17000, 24000)];
        self::assertSame(36500 * 12, (new DerivedTierAllocator)->allocate($items, 1500)->exactSubtotalNumerator);
    }

    public function test_allocator_matches_independent_exhaustive_oracle_and_conserves_units(): void
    {
        $allocator = new DerivedTierAllocator;
        foreach ([6, 7, 8, 10, 12, 18, 24, 25, 30] as $total) {
            for ($a = 1; $a <= min(8, $total - 2); $a++) {
                $b = min(7, $total - $a - 1);
                $items = [$this->product(1, $a, 3000, 12500, 24000), $this->product(2, $b, 2500, 14000, 22000), $this->product(3, $total - $a - $b, 2100, 12000, 20000)];
                $quote = $allocator->allocate($items, 1500);
                self::assertSame($this->oracle($items, 1500), $quote->exactSubtotalNumerator);
                self::assertSame($quote->allocations, $allocator->allocate(array_reverse($items), 1500)->allocations);
                self::assertSame($quote->exactSubtotalNumerator, $allocator->allocate(array_reverse($items), 1500)->exactSubtotalNumerator);
                foreach ($items as $item) {
                    self::assertSame($item['quantity'], array_sum($quote->allocations[$item['product_id']]));
                }
            }
        }
    }

    /** Enumerate flavor counts directly, independently from production DP and its homogeneous/mixed searches. */
    private function oracle(array $items, int $surcharge): int
    {
        $remainder = array_sum(array_column($items, 'quantity')) % 12;
        $halves = $remainder >= 6 ? 6 : 0;
        $units = $remainder % 6;
        $minimum = PHP_INT_MAX;
        $visit = function (int $index, array $half, int $leftHalf, int $leftUnit, int $cost) use (&$visit, &$minimum, $items, $surcharge): void {
            if ($index === count($items)) {
                if ($leftHalf !== 0 || $leftUnit !== 0) {
                    return;
                }
                $members = array_filter($half);
                if (count($members) === 1) {
                    $id = array_key_first($members);
                    $p = $items[$id];
                    if ($p['half_dozen_price'] >= 6 * $p['unit_price'] || 2 * $p['half_dozen_price'] < $p['dozen_price']) {
                        return;
                    }
                    $cost += 12 * $p['half_dozen_price'] - 6 * $p['dozen_price'];
                } elseif (count($members) > 1) {
                    foreach ($members as $id => $quantity) {
                        if ($items[$id]['dozen_price'] + 2 * $surcharge >= 12 * $items[$id]['unit_price']) {
                            return;
                        }
                    }
                    $cost += 12 * $surcharge;
                }
                $minimum = min($minimum, $cost);

                return;
            }
            $p = $items[$index];
            for ($h = 0; $h <= min($p['quantity'], $leftHalf); $h++) {
                for ($u = 0; $u <= min($p['quantity'] - $h, $leftUnit); $u++) {
                    $d = $p['quantity'] - $h - $u;
                    if ($d > 0 && $p['dozen_price'] >= 12 * $p['unit_price']) {
                        continue;
                    }
                    $visit($index + 1, [...$half, $h], $leftHalf - $h, $leftUnit - $u, $cost + ($d + $h) * $p['dozen_price'] + $u * 12 * $p['unit_price']);
                }
            }
        };
        $visit(0, [], $halves, $units, 0);

        return $minimum;
    }
}
