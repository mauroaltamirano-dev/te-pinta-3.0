<?php

namespace App\Modules\Products\Pricing;

use App\Core\Contracts\ProductPriceProvider;
use App\Core\Data\SellableProductPrice;
use App\Modules\Orders\Enums\OrderSettingKey;
use App\Modules\Orders\Models\OrderSetting;
use App\Modules\Products\Actions\CalculateProductPrice;
use App\Modules\Products\Enums\PriceTier;
use App\Modules\Products\Enums\ProductPricingFamily;
use App\Modules\Products\Models\Product;
use App\Modules\Products\Models\RawPriceDiscount;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\ValidationException;

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

        if (! $product || ($product->pricing_family === ProductPricingFamily::Unit && $tier !== PriceTier::Unit)) {
            return null;
        }

        return $this->calculateProductPrice->execute($product, $tier, $isCooked);
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
     * Cooked empanadas charge their stored dozen and unit prices. Raw (cook at home) takes the dozen
     * discount and applies the same unit formula: D_raw = D - discount, U_raw = EmpanadaUnitPrice(D_raw).
     * Unit and half-dozen raw discounts are no longer used. Invalid configuration is a validation error.
     *
     * @param  Collection<int, Product>  $products
     * @return array<int, SellableProductPrice>
     */
    private function pricesForProducts($products, bool $isCooked): array
    {
        $needsRaw = ! $isCooked && $products->contains(fn (Product $product): bool => $product->pricing_family === ProductPricingFamily::Empanada);
        $dozenDiscount = null;
        $surchargePercent = null;
        if ($needsRaw) {
            $dozenDiscount = RawPriceDiscount::query()->where('pricing_mode', PriceTier::Dozen->value)->value('amount');
            if ($dozenDiscount === null) {
                throw ValidationException::withMessages(['items' => 'Falta configurar el descuento de docena para cocinar en casa.']);
            }
            $surchargePercent = OrderSetting::integer(OrderSettingKey::EmpanadaUnitSurchargePercent);
        }

        return $products->map(function (Product $product) use ($dozenDiscount, $surchargePercent, $needsRaw): SellableProductPrice {
            $unitPrice = $product->unit_price;
            $dozenPrice = $product->dozen_price;
            if ($needsRaw && $product->pricing_family === ProductPricingFamily::Empanada) {
                $dozenPrice -= (int) $dozenDiscount;
                if ($dozenPrice < 0) {
                    throw ValidationException::withMessages(['items' => "El descuento para cocinar en casa supera la docena de {$product->name}."]);
                }
                $unitPrice = EmpanadaUnitPrice::fromDozen($dozenPrice, (int) $surchargePercent);
            }

            return new SellableProductPrice(
                id: $product->id,
                slug: $product->slug,
                name: $product->name,
                pricingFamily: $product->pricing_family->value,
                unitPrice: $unitPrice,
                // Half dozens do not price orders; the stored value is passed through unchanged.
                halfDozenPrice: $product->half_dozen_price,
                dozenPrice: $dozenPrice,
                cookedUnitPrice: $product->unit_price,
                cookedHalfDozenPrice: $product->half_dozen_price,
                cookedDozenPrice: $product->dozen_price,
            );
        })->values()->all();
    }
}
