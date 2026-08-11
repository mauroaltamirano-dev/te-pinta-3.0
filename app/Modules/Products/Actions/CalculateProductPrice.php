<?php

namespace App\Modules\Products\Actions;

use App\Modules\Products\Enums\PriceTier;
use App\Modules\Products\Models\CookingSurcharge;
use App\Modules\Products\Models\Product;
use LogicException;

final class CalculateProductPrice
{
    public function execute(Product $product, PriceTier $tier, bool $isCooked = false): int
    {
        $basePrice = match ($tier) {
            PriceTier::Unit => $product->unit_price,
            PriceTier::HalfDozen => $product->half_dozen_price,
            PriceTier::Dozen => $product->dozen_price,
        };

        if (! $isCooked) {
            return $basePrice;
        }

        $surcharge = CookingSurcharge::query()
            ->where('pricing_mode', $tier->value)
            ->value('amount');

        if ($surcharge === null) {
            throw new LogicException("Missing cooking surcharge for {$tier->value}.");
        }

        return $basePrice + (int) $surcharge;
    }
}
