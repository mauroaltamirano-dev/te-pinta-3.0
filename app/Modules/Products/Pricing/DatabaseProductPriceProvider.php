<?php

namespace App\Modules\Products\Pricing;

use App\Core\Contracts\ProductPriceProvider;
use App\Core\Data\SellableProductPrice;
use App\Modules\Orders\Enums\OrderSettingKey;
use App\Modules\Orders\Models\OrderSetting;
use App\Modules\Products\Enums\PriceTier;
use App\Modules\Products\Enums\ProductPricingFamily;
use App\Modules\Products\Models\Product;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\ValidationException;

final readonly class DatabaseProductPriceProvider implements ProductPriceProvider
{
    public function priceFor(int $productId, string $pricingMode, bool $isCooked): ?int
    {
        $tier = PriceTier::tryFrom($pricingMode);
        $price = $tier === null ? null : $this->productForSale($productId, $isCooked);

        if ($price === null || ($price->pricingFamily === ProductPricingFamily::Unit->value && $tier !== PriceTier::Unit)) {
            return null;
        }

        return match ($tier) {
            PriceTier::Unit => $price->unitPrice,
            // Raw half dozens are six raw units; cooked ones keep the stored price.
            PriceTier::HalfDozen => $isCooked ? $price->halfDozenPrice : EmpanadaUnitPrice::halfDozen($price->unitPrice),
            PriceTier::Dozen => $price->dozenPrice,
        };
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
     * Cooked empanadas charge their stored dozen and unit prices. Raw (cook at home) takes the raw
     * percentage off the dozen and applies the same unit formula: D_raw = D × (100 − p) / 100,
     * U_raw = EmpanadaUnitPrice(D_raw). Invalid configuration is a validation error.
     *
     * @param  Collection<int, Product>  $products
     * @return array<int, SellableProductPrice>
     */
    private function pricesForProducts($products, bool $isCooked): array
    {
        $needsRaw = ! $isCooked && $products->contains(fn (Product $product): bool => $product->pricing_family === ProductPricingFamily::Empanada);
        $rawPercent = null;
        $surchargePercent = null;
        if ($needsRaw) {
            $rawPercent = OrderSetting::query()->where('key', OrderSettingKey::RawDiscountPercent->value)->value('integer_value');
            if ($rawPercent === null || $rawPercent < 0 || $rawPercent > 100) {
                throw ValidationException::withMessages(['items' => 'Falta configurar el descuento para cocinar en casa.']);
            }
            $surchargePercent = OrderSetting::integer(OrderSettingKey::EmpanadaUnitSurchargePercent);
        }

        return $products->map(function (Product $product) use ($rawPercent, $surchargePercent, $needsRaw): SellableProductPrice {
            $unitPrice = $product->unit_price;
            $dozenPrice = $product->dozen_price;
            if ($needsRaw && $product->pricing_family === ProductPricingFamily::Empanada) {
                $dozenPrice = intdiv($dozenPrice * (100 - (int) $rawPercent), 100);
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
