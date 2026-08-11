<?php

namespace App\Modules\Products\Pricing;

use App\Core\Contracts\ProductPriceProvider;
use App\Modules\Products\Actions\CalculateProductPrice;
use App\Modules\Products\Enums\PriceTier;
use App\Modules\Products\Models\Product;

final readonly class DatabaseProductPriceProvider implements ProductPriceProvider
{
    public function __construct(private CalculateProductPrice $calculateProductPrice) {}

    public function priceFor(int $productId, string $pricingMode, bool $isCooked): ?int
    {
        $tier = PriceTier::tryFrom($pricingMode);

        if (! $tier) {
            return null;
        }

        $product = Product::query()
            ->whereKey($productId)
            ->where('is_available', true)
            ->first();

        return $product
            ? $this->calculateProductPrice->execute($product, $tier, $isCooked)
            : null;
    }
}
