<?php

namespace App\Modules\Products\Pricing;

use App\Core\Contracts\ProductPriceProvider;
use App\Core\Data\SellableProductPrice;
use App\Modules\Products\Actions\CalculateProductPrice;
use App\Modules\Products\Enums\PriceTier;
use App\Modules\Products\Models\CookingSurcharge;
use App\Modules\Products\Models\Product;
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
        $surcharges = $isCooked
            ? CookingSurcharge::query()->pluck('amount', 'pricing_mode')->map(fn ($amount): int => (int) $amount)->all()
            : [];

        if ($isCooked) {
            foreach (PriceTier::cases() as $tier) {
                if (! array_key_exists($tier->value, $surcharges)) {
                    throw new LogicException("Missing cooking surcharge for {$tier->value}.");
                }
            }
        }

        return $products->map(fn (Product $product): SellableProductPrice => new SellableProductPrice(
            id: $product->id,
            slug: $product->slug,
            name: $product->name,
            unitPrice: $product->unit_price + ($surcharges[PriceTier::Unit->value] ?? 0),
            halfDozenPrice: $product->half_dozen_price + ($surcharges[PriceTier::HalfDozen->value] ?? 0),
            dozenPrice: $product->dozen_price + ($surcharges[PriceTier::Dozen->value] ?? 0),
        ))->values()->all();
    }
}
