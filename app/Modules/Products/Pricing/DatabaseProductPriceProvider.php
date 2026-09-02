<?php

namespace App\Modules\Products\Pricing;

use App\Core\Contracts\ProductPriceProvider;
use App\Core\Data\SellableProductPrice;
use App\Modules\Products\Actions\CalculateProductPrice;
use App\Modules\Products\Enums\PriceTier;
use App\Modules\Products\Models\Product;
use App\Modules\Products\Models\RawPriceDiscount;
use Illuminate\Database\Eloquent\Collection;
use LogicException;

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
            ->whereHas('category', fn ($query) => $query->where('is_active', true))
            ->first();

        return $product
            ? $this->calculateProductPrice->execute($product, $tier, $isCooked)
            : null;
    }

    public function productForSale(int $productId, bool $isCooked): ?SellableProductPrice
    {
        return $this->productsForSale([$productId], $isCooked)[$productId] ?? null;
    }

    public function productsForSale(array $productIds, bool $isCooked): array
    {
        $products = Product::query()
            ->whereKey($productIds)
            ->where('is_available', true)
            ->whereHas('category', fn ($query) => $query->where('is_active', true))
            ->get();

        $prices = [];
        foreach ($this->pricesForProducts($products, $isCooked) as $price) {
            $prices[$price->id] = $price;
        }

        return $prices;
    }

    public function productsForSaleBySlugs(array $productSlugs, bool $isCooked): array
    {
        $products = Product::query()
            ->whereIn('slug', array_values(array_unique($productSlugs)))
            ->where('is_available', true)
            ->whereHas('category', fn ($query) => $query->where('is_active', true))
            ->get();

        $prices = [];
        foreach ($this->pricesForProducts($products, $isCooked) as $price) {
            $prices[$price->slug] = $price;
        }

        return $prices;
    }

    /**
     * @param  Collection<int, Product>  $products
     * @return array<int, SellableProductPrice>
     */
    private function pricesForProducts($products, bool $isCooked): array
    {
        $discounts = RawPriceDiscount::query()
            ->pluck('amount', 'pricing_mode')
            ->map(fn ($amount): int => (int) $amount)
            ->all();

        foreach (PriceTier::cases() as $tier) {
            if (! array_key_exists($tier->value, $discounts)) {
                throw new LogicException("Falta el descuento de precio crudo para {$tier->value}.");
            }
        }

        return $products->map(function (Product $product) use ($discounts, $isCooked): SellableProductPrice {
            $rawUnitPrice = $product->unit_price - $discounts[PriceTier::Unit->value];
            $rawHalfDozenPrice = $product->half_dozen_price - $discounts[PriceTier::HalfDozen->value];
            $rawDozenPrice = $product->dozen_price - $discounts[PriceTier::Dozen->value];

            if (min($rawUnitPrice, $rawHalfDozenPrice, $rawDozenPrice) < 0) {
                throw new LogicException('Un descuento de precio crudo supera el precio cocinado de un producto.');
            }

            return new SellableProductPrice(
                id: $product->id,
                slug: $product->slug,
                name: $product->name,
                unitPrice: $isCooked ? $product->unit_price : $rawUnitPrice,
                halfDozenPrice: $isCooked ? $product->half_dozen_price : $rawHalfDozenPrice,
                dozenPrice: $isCooked ? $product->dozen_price : $rawDozenPrice,
                cookedUnitPrice: $product->unit_price,
                cookedHalfDozenPrice: $product->half_dozen_price,
                cookedDozenPrice: $product->dozen_price,
            );
        })->values()->all();
    }
}
