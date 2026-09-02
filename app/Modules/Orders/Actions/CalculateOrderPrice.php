<?php

namespace App\Modules\Orders\Actions;

use App\Core\Contracts\ProductPriceProvider;
use App\Modules\Orders\Enums\PricingMode;
use Illuminate\Validation\ValidationException;

final readonly class CalculateOrderPrice
{
    public function __construct(private ProductPriceProvider $prices) {}

    /** @param array<int, array{product_id: int, pricing_mode: string, quantity: int, is_cooked?: bool}> $items
     * @return array{items: array<int, array<string, int|string|bool>>, total: int}
     */
    public function execute(array $items): array
    {
        $lines = [];
        $total = 0;
        foreach ($items as $item) {
            $mode = PricingMode::from($item['pricing_mode']);
            $price = $this->prices->priceFor($item['product_id'], $mode->value, $item['is_cooked'] ?? true);
            if ($price === null) {
                throw ValidationException::withMessages(['items' => 'Todos los productos deben estar disponibles.']);
            }
            $lineTotal = $price * $item['quantity'];
            $total += $lineTotal;
            $lines[] = [...$item, 'is_cooked' => $item['is_cooked'] ?? true, 'unit_price' => $price, 'line_total' => $lineTotal];
        }

        return ['items' => $lines, 'total' => $total];
    }
}
