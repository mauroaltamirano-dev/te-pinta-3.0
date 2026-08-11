<?php

namespace App\Core\Contracts;

interface ProductPriceProvider
{
    public function priceFor(int $productId, string $pricingMode, bool $isCooked): ?int;
}
