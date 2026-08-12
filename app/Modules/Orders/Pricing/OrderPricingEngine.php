<?php

namespace App\Modules\Orders\Pricing;

use App\Core\Contracts\ProductPriceProvider;
use App\Core\Data\SellableProductPrice;
use App\Modules\Orders\Enums\DeliveryType;
use App\Modules\Orders\Enums\OrderCondition;
use App\Modules\Orders\Enums\OrderSettingKey;
use App\Modules\Orders\Models\OrderAddon;
use App\Modules\Orders\Models\OrderSetting;
use Illuminate\Validation\ValidationException;

final readonly class OrderPricingEngine
{
    public function __construct(private ProductPriceProvider $productPrices) {}

    /**
     * @param  array<int, array{product_id: int, quantity: int}>  $items
     * @param  array<int, array{addon_id: int, quantity: int}>  $addons
     */
    public function quote(
        array $items,
        OrderCondition $condition,
        array $addons = [],
        DeliveryType $deliveryType = DeliveryType::Pickup,
    ): OrderPriceQuote {
        $quantities = [];

        foreach ($items as $item) {
            $productId = $item['product_id'];
            $quantity = $item['quantity'];

            if ($quantity < 1 || $quantity > 120 || isset($quantities[$productId])) {
                throw ValidationException::withMessages(['items' => 'Products must be unique with quantities between 1 and 120.']);
            }

            $quantities[$productId] = $quantity;
        }

        if ($quantities === []) {
            throw ValidationException::withMessages(['items' => 'At least one product is required.']);
        }

        ksort($quantities);
        $products = $this->productPrices->productsForSale(
            array_keys($quantities),
            $condition === OrderCondition::Cooked,
        );
        if (count($products) !== count($quantities)) {
            throw ValidationException::withMessages(['items' => 'Every product must be available.']);
        }
        $lines = [];

        // Product-specific dozens have priority because they retain the flavor's own commercial price.
        foreach ($quantities as $productId => &$quantity) {
            $dozens = intdiv($quantity, 12);
            if ($dozens > 0) {
                $lines[] = $this->productLine($products[$productId], 'dozen', $dozens, 12, $products[$productId]->dozenPrice);
                $quantity %= 12;
            }
        }
        unset($quantity);

        // Mixed blocks are closed before half-dozens. Product-id order makes equivalent groupings stable.
        $combinedPrice = OrderSetting::integer($condition === OrderCondition::Cooked
            ? OrderSettingKey::CombinedDozenCookedPrice
            : OrderSettingKey::CombinedDozenRawPrice);
        while (array_sum($quantities) >= 12 && count(array_filter($quantities)) > 1) {
            $composition = $this->takeCombinedDozen($quantities, $products);
            $regularPrice = 0;
            foreach ($composition as $part) {
                $regularPrice += $this->normalPrice($products[$part['product_id']], $part['quantity']);
            }
            $savings = max(0, $regularPrice - $combinedPrice);
            $lines[] = new PricingLine(
                pricingMode: 'combined_dozen', quantity: 1, unitsCount: 12,
                unitPrice: $combinedPrice, lineTotal: $combinedPrice,
                regularPriceTotal: $regularPrice, savings: $savings,
                composition: $composition,
            );
        }

        foreach ($quantities as $productId => $quantity) {
            $product = $products[$productId];
            $halves = intdiv($quantity, 6);
            if ($halves > 0) {
                $lines[] = $this->productLine($product, 'half_dozen', $halves, 6, $product->halfDozenPrice);
            }
            $units = $quantity % 6;
            if ($units > 0) {
                $lines[] = $this->productLine($product, 'unit', $units, 1, $product->unitPrice);
            }
        }

        $pricedAddons = $this->priceAddons($addons);
        $productsSubtotal = array_sum(array_map(fn (PricingLine $line): int => $line->lineTotal, $lines));
        $addonsSubtotal = array_sum(array_map(fn (PricingAddon $addon): int => $addon->lineTotal, $pricedAddons));
        $shippingFee = $deliveryType === DeliveryType::Delivery
            ? OrderSetting::integer(OrderSettingKey::DeliveryFee)
            : 0;
        $savings = array_sum(array_map(fn (PricingLine $line): int => $line->savings, $lines));

        return new OrderPriceQuote(
            $condition, $deliveryType, $lines, $pricedAddons,
            $productsSubtotal, $addonsSubtotal, $shippingFee, $savings,
            $productsSubtotal + $addonsSubtotal + $shippingFee,
        );
    }

    private function productLine(SellableProductPrice $product, string $mode, int $quantity, int $units, int $price): PricingLine
    {
        return new PricingLine(
            pricingMode: $mode, quantity: $quantity, unitsCount: $quantity * $units,
            unitPrice: $price, lineTotal: $quantity * $price,
            regularPriceTotal: $quantity * $price, savings: 0,
            productId: $product->id, productName: $product->name,
        );
    }

    /**
     * @param  array<int, int>  $quantities
     * @param  array<int, SellableProductPrice>  $products
     * @return array<int, array{product_id: int, product_name: string, quantity: int}>
     */
    private function takeCombinedDozen(array &$quantities, array $products): array
    {
        $remaining = 12;
        $composition = [];
        foreach ($quantities as $productId => &$quantity) {
            if ($quantity === 0 || $remaining === 0) {
                continue;
            }
            $taken = min($quantity, $remaining);
            $quantity -= $taken;
            $remaining -= $taken;
            $composition[] = ['product_id' => $productId, 'product_name' => $products[$productId]->name, 'quantity' => $taken];
        }
        unset($quantity);

        return $composition;
    }

    private function normalPrice(SellableProductPrice $product, int $quantity): int
    {
        $dozens = intdiv($quantity, 12);
        $remainder = $quantity % 12;
        $halves = intdiv($remainder, 6);
        $units = $remainder % 6;

        return ($dozens * $product->dozenPrice) + ($halves * $product->halfDozenPrice) + ($units * $product->unitPrice);
    }

    /** @param array<int, array{addon_id: int, quantity: int}> $addons
     * @return array<int, PricingAddon>
     */
    private function priceAddons(array $addons): array
    {
        $priced = [];
        $seen = [];
        foreach ($addons as $input) {
            if ($input['quantity'] < 1 || $input['quantity'] > 100 || isset($seen[$input['addon_id']])) {
                throw ValidationException::withMessages(['addons' => 'Addons must be unique with quantities between 1 and 100.']);
            }
            $seen[$input['addon_id']] = true;
            $addon = OrderAddon::query()->whereKey($input['addon_id'])->where('is_available', true)->first();
            if (! $addon) {
                throw ValidationException::withMessages(['addons' => 'Every addon must be available.']);
            }
            $priced[] = new PricingAddon($addon->id, $addon->name, $input['quantity'], $addon->price, $input['quantity'] * $addon->price);
        }

        return $priced;
    }
}
