<?php

namespace App\Core\Contracts;

use App\Core\Data\SellableProductPrice;

interface ProductPriceProvider
{
    public function priceFor(int $productId, string $pricingMode, bool $isCooked): ?int;

    public function productForSale(int $productId, bool $isCooked): ?SellableProductPrice;

    /** @param array<int, int> $productIds
     * @return array<int, SellableProductPrice>
     */
    public function productsForSale(array $productIds, bool $isCooked): array;

    /** @param array<int, string> $productSlugs
     * @return array<string, SellableProductPrice>
     */
    public function productsForSaleBySlugs(array $productSlugs, bool $isCooked): array;
}
