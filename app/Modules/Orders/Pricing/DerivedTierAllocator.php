<?php

namespace App\Modules\Orders\Pricing;

use InvalidArgumentException;

/**
 * Optimize fixed commercial slots, not arbitrary package counts.
 *
 * @phpstan-type Product array{product_id: int, quantity: int, unit_price: int, half_dozen_price: int, dozen_price: int, pricing_family: string, name: string}
 * @phpstan-type Counts array{dozen_units: int, half_dozen_units: int, unit_units: int}
 * @phpstan-type State array{cost: int, allocations: array<int, Counts>}
 */
final class DerivedTierAllocator
{
    /** @param list<Product> $items */
    public function allocate(array $items, int $combinedSurcharge): DerivedTierAllocation
    {
        if ($items === [] || count($items) > 50 || $combinedSurcharge < 0 || $combinedSurcharge > 2147483647) {
            throw new InvalidArgumentException('Invalid empanada basket or combined half-dozen surcharge.');
        }
        $products = [];
        foreach ($items as $item) {
            if (! $this->integerInputs($item['product_id'], $item['quantity'], $item['unit_price'], $item['half_dozen_price'], $item['dozen_price'])
                || $item['pricing_family'] !== 'empanada' || $item['product_id'] < 1 || isset($products[$item['product_id']])
                || $item['quantity'] < 1 || $item['quantity'] > 120
                || min($item['unit_price'], $item['half_dozen_price'], $item['dozen_price']) < 0
                || max($item['unit_price'], $item['half_dozen_price'], $item['dozen_price']) > 2147483647) {
                throw new InvalidArgumentException('Invalid or duplicate empanada pricing input.');
            }
            $products[$item['product_id']] = $item;
        }
        ksort($products);
        $remainder = array_sum(array_column($products, 'quantity')) % 12;
        $halfTarget = $remainder >= 6 ? 6 : 0;
        $unitTarget = $remainder % 6;
        $best = null;

        if ($halfTarget === 0) {
            $best = $this->search($products, 0, $unitTarget, $combinedSurcharge);
        } else {
            // A homogeneous six is a whole-product tariff, not six mixed shares.
            foreach ($products as $id => $product) {
                if ($product['quantity'] < 6 || $product['half_dozen_price'] >= 6 * $product['unit_price']
                    || 2 * $product['half_dozen_price'] < $product['dozen_price']) {
                    continue;
                }
                $remaining = $products;
                $remaining[$id] = [...$product, 'quantity' => $product['quantity'] - 6];
                $candidate = $this->search($remaining, 0, $unitTarget, $combinedSurcharge);
                if ($candidate !== null) {
                    $candidate['cost'] += 12 * $product['half_dozen_price'];
                    $counts = $candidate['allocations'][$id];
                    $candidate['allocations'][$id] = [...$counts, 'half_dozen_units' => 6];
                    if ($best === null || $candidate['cost'] < $best['cost']) {
                        $best = $candidate;
                    }
                }
            }
            $mixed = $this->search($products, 6, $unitTarget, $combinedSurcharge);
            if ($mixed !== null) {
                $mixed['cost'] += 12 * $combinedSurcharge;
                if ($best === null || $mixed['cost'] < $best['cost']) {
                    $best = $mixed;
                }
            }
        }

        if ($best === null) {
            $issues = [];
            foreach ($products as $product) {
                $fields = [];
                if ($product['dozen_price'] >= 12 * $product['unit_price']) {
                    $fields[] = 'dozen_price';
                }
                if ($product['half_dozen_price'] >= 6 * $product['unit_price'] || 2 * $product['half_dozen_price'] < $product['dozen_price']) {
                    $fields[] = 'homogeneous_half_dozen_surcharge / half_dozen_price';
                }
                if (12 * $product['unit_price'] <= $product['dozen_price'] + 2 * $combinedSurcharge) {
                    $fields[] = 'combined_half_dozen_surcharge / dozen_price';
                }
                if ($fields !== []) {
                    $issues[] = $product['name'].': '.implode(', ', $fields);
                }
            }
            $names = implode('; ', $issues);
            throw new InvalidArgumentException("No valid commercial allocation for {$names}; check unit, dozen, homogeneous and combined half-dozen configuration for the selected condition.");
        }
        if ($best['cost'] > ExactOrderTotals::MAX_STORED_PESOS * 12) {
            throw new InvalidArgumentException('The product subtotal exceeds order storage.');
        }

        return new DerivedTierAllocation($best['cost'], $best['allocations']);
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

    /**
     * For mixed searches the state retains the first half flavor (0=none, -1=mixed).
     * Discarding this identity would incorrectly admit homogeneous sixes at the mixed tariff.
     *
     * @param  array<int, Product>  $products
     * @return State|null
     */
    private function search(array $products, int $halfTarget, int $unitTarget, int $surcharge): ?array
    {
        $states = ['0:0:0' => ['cost' => 0, 'allocations' => []]];
        foreach ($products as $id => $product) {
            $next = [];
            foreach ($states as $key => $state) {
                [$usedHalf, $usedUnit, $first] = array_map('intval', explode(':', $key));
                for ($h = 0; $h <= min($product['quantity'], $halfTarget - $usedHalf); $h++) {
                    if ($h > 0 && 12 * $product['unit_price'] <= $product['dozen_price'] + 2 * $surcharge) {
                        continue;
                    }
                    for ($u = 0; $u <= min($product['quantity'] - $h, $unitTarget - $usedUnit); $u++) {
                        $d = $product['quantity'] - $h - $u;
                        if ($d > 0 && $product['dozen_price'] >= 12 * $product['unit_price']) {
                            continue;
                        }
                        $identity = $h === 0 ? $first : ($first === 0 ? $id : -1);
                        $nextKey = ($usedHalf + $h).':'.($usedUnit + $u).':'.$identity;
                        $cost = $state['cost'] + ($d + $h) * $product['dozen_price'] + $u * 12 * $product['unit_price'];
                        if (isset($next[$nextKey]) && $next[$nextKey]['cost'] <= $cost) {
                            continue;
                        }
                        $allocations = $state['allocations'];
                        $allocations[$id] = ['dozen_units' => $d, 'half_dozen_units' => $h, 'unit_units' => $u];
                        $next[$nextKey] = ['cost' => $cost, 'allocations' => $allocations];
                    }
                }
            }
            $states = $next;
        }

        return $states[$halfTarget.':'.$unitTarget.':'.($halfTarget === 0 ? 0 : -1)] ?? null;
    }
}
