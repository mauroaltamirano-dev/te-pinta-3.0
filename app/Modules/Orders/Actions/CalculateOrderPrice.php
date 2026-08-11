<?php

namespace App\Modules\Orders\Actions;

use App\Core\Contracts\ProductPriceProvider;
use App\Modules\Orders\Enums\PricingMode;
use Illuminate\Validation\ValidationException;

final class CalculateOrderPrice
{
    public function __construct(private readonly ProductPriceProvider $productPriceProvider) {}

    /**
     * @param  array<int, array{product_id: int, pricing_mode: string, quantity: int, is_cooked?: bool}>  $items
     * @return array{items: array<int, array{product_id: int, pricing_mode: string, quantity: int, is_cooked: bool, unit_price: int, line_total: int}>, total: int}
     */
    public function execute(array $items): array
    {
        $lines = [];
        $total = 0;

        foreach ($items as $item) {
            $mode = PricingMode::from($item['pricing_mode']);
            $isCooked = $item['is_cooked'] ?? false;
            $price = $this->productPriceProvider->priceFor($item['product_id'], $mode->value, $isCooked);

            if ($price === null) {
                throw ValidationException::withMessages(['items' => 'Every product must be available.']);
            }

            $lineTotal = $price * $item['quantity'];
            $total += $lineTotal;

            $lines[] = [
                'product_id' => $item['product_id'],
                'pricing_mode' => $mode->value,
                'quantity' => $item['quantity'],
                'is_cooked' => $isCooked,
                'unit_price' => $price,
                'line_total' => $lineTotal,
            ];
        }

        return ['items' => $lines, 'total' => $total];
    }
}
