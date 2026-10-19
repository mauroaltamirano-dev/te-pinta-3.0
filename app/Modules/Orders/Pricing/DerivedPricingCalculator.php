<?php

namespace App\Modules\Orders\Pricing;

use InvalidArgumentException;

/**
 * Future shared-engine primitive. Not wired to any HTTP/persistence flow in P0-A.
 * Inputs already contain effective prices for exactly one preparation condition.
 *
 * @phpstan-import-type Product from DerivedTierAllocator
 * @phpstan-import-type Line from DerivedPricingQuote
 */
final class DerivedPricingCalculator
{
    public function __construct(private readonly DerivedTierAllocator $allocator = new DerivedTierAllocator) {}

    /** @param list<Product> $products */
    public function quote(array $products, int $combinedSurcharge): DerivedPricingQuote
    {
        if ($products === [] || count($products) > 50 || $combinedSurcharge < 0 || $combinedSurcharge > 2147483647) {
            throw new InvalidArgumentException('Invalid basket or combined half-dozen surcharge.');
        }
        $empanadas = [];
        $byId = [];
        $lines = [];
        $baseline = 0;
        foreach ($products as $product) {
            $id = $product['product_id'];
            if (! $this->integerInputs($id, $product['quantity'], $product['unit_price']) || $id < 1 || isset($byId[$id]) || $product['quantity'] < 1 || $product['quantity'] > 120
                || $product['unit_price'] < 0 || $product['unit_price'] > 2147483647
                || ! in_array($product['pricing_family'], ['empanada', 'unit'], true)) {
                throw new InvalidArgumentException('Invalid or duplicate product.');
            }
            $byId[$id] = $product;
            $baseline += $product['quantity'] * $product['unit_price'];
            if ($product['pricing_family'] === 'empanada') {
                $empanadas[] = $product;
            }
        }
        ksort($byId);
        $grouped = false;
        $allocations = [];
        if ($empanadas !== []) {
            $allocation = $this->allocator->allocate($empanadas, $combinedSurcharge);
            $allocations = $allocation->allocations;
            foreach (['dozen_units' => 12, 'half_dozen_units' => 6] as $key => $size) {
                $members = [];
                foreach ($allocation->allocations as $id => $counts) {
                    if ($counts[$key] > 0) {
                        $members[$id] = $counts[$key];
                    }
                }
                while (array_sum($members) > 0) {
                    $composition = [];
                    $remaining = $size;
                    foreach ($members as $id => &$count) {
                        $take = min($count, $remaining);
                        if ($take > 0) {
                            $composition[$id] = $take;
                            $count -= $take;
                            $remaining -= $take;
                        }
                    }
                    unset($count);
                    if ($composition === [] || $remaining !== 0) {
                        throw new \LogicException('Allocation did not fill a commercial block.');
                    }
                    $mixed = count($composition) > 1;
                    $mode = $size === 12 ? 'dozen' : 'half_dozen';
                    if ($size === 6 && ! $mixed) {
                        $numerator = $byId[array_key_first($composition)]['half_dozen_price'] * 12;
                    } else {
                        $numerator = $size === 6 ? 12 * $combinedSurcharge : 0;
                        foreach ($composition as $id => $count) {
                            $numerator += $count * $byId[$id]['dozen_price'];
                        }
                    }
                    $lines[] = $this->line(($mixed ? 'combined_' : '').$mode, $size, $composition, $numerator);
                    $grouped = true;
                }
            }
        }
        foreach ($byId as $id => $product) {
            $units = $product['pricing_family'] === 'unit' ? $product['quantity'] : $allocations[$id]['unit_units'];
            if ($units > 0) {
                $lines[] = $this->line('unit', $units, [$id => $units], $units * $product['unit_price'] * 12);
            }
        }
        $numerator = array_sum(array_column($lines, 'exact_numerator_12'));
        // Products-only peso projection; the order total is rounded later, once, by ExactOrderTotals.
        $productsSubtotal = intdiv($numerator + 6, 12);
        if ($grouped && $productsSubtotal >= $baseline) {
            throw new InvalidArgumentException('Commercial savings disappear after peso rounding; review the participating prices and surcharges.');
        }
        // Largest-remainder projection affects display only; exact fractions remain authoritative.
        $priority = array_keys($lines);
        usort($priority, fn (int $a, int $b): int => ($lines[$b]['exact_numerator_12'] % 12 <=> $lines[$a]['exact_numerator_12'] % 12) ?: ($a <=> $b));
        $left = $productsSubtotal - array_sum(array_column($lines, 'line_total'));
        foreach (array_slice($priority, 0, $left) as $index) {
            $line = $lines[$index];
            $lines[$index] = [...$line, 'line_total' => $line['line_total'] + 1];
        }

        return new DerivedPricingQuote($numerator, $baseline, $lines, $productsSubtotal, $productsSubtotal, $productsSubtotal * 1200 - $numerator * 100);
    }

    private function integerInputs(mixed ...$values): bool
    {
        foreach ($values as $value) {
            if (! is_int($value)) {
                return false;
            }
        }

        return true;
    }

    /** @param array<int, int> $composition
     * @return Line
     */
    private function line(string $mode, int $units, array $composition, int $numerator): array
    {
        return ['mode' => $mode, 'units' => $units, 'composition' => $composition, 'exact_numerator_12' => $numerator, 'line_total' => intdiv($numerator, 12)];
    }
}
