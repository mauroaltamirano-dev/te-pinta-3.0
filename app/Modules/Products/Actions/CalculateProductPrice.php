<?php

namespace App\Modules\Products\Actions;

use App\Modules\Products\Enums\PriceTier;
use App\Modules\Products\Models\Product;
use App\Modules\Products\Models\RawPriceDiscount;
use LogicException;

final class CalculateProductPrice
{
    public function execute(Product $product, PriceTier $tier, bool $isCooked = true): int
    {
        $basePrice = match ($tier) {
            PriceTier::Unit => $product->unit_price,
            PriceTier::HalfDozen => $product->half_dozen_price,
            PriceTier::Dozen => $product->dozen_price,
        };

        if ($isCooked) {
            return $basePrice;
        }

        $discount = RawPriceDiscount::query()
            ->where('pricing_mode', $tier->value)
            ->value('amount');

        if ($discount === null) {
            throw new LogicException("Falta el descuento de precio crudo para {$tier->value}.");
        }

        if ((int) $discount > $basePrice) {
            throw new LogicException("El descuento de precio crudo supera el precio cocinado para {$tier->value}.");
        }

        return $basePrice - (int) $discount;
    }
}
