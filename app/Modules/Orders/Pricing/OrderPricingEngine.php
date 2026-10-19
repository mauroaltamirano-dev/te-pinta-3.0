<?php

namespace App\Modules\Orders\Pricing;

use App\Core\Contracts\ProductPriceProvider;
use App\Core\Data\SellableProductPrice;
use App\Modules\Orders\Enums\DeliveryType;
use App\Modules\Orders\Enums\DiscountType;
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
        ?DiscountType $discountType = null,
        ?int $discountValue = null,
    ): OrderPriceQuote {
        $quantities = [];

        foreach ($items as $item) {
            $productId = $item['product_id'];
            $quantity = $item['quantity'];

            if ($quantity < 1 || $quantity > 120 || isset($quantities[$productId])) {
                throw ValidationException::withMessages(['items' => 'Los productos no pueden repetirse y sus cantidades deben estar entre 1 y 120.']);
            }

            $quantities[$productId] = $quantity;
        }

        if ($quantities === []) {
            throw ValidationException::withMessages(['items' => 'Se requiere al menos un producto.']);
        }
        if (count($quantities) > 50) {
            throw ValidationException::withMessages(['items' => 'Un pedido admite como máximo 50 productos distintos.']);
        }

        ksort($quantities);
        $products = $this->productPrices->productsForSale(
            array_keys($quantities),
            $condition === OrderCondition::Cooked,
        );
        if (count($products) !== count($quantities)) {
            throw ValidationException::withMessages(['items' => 'Todos los productos deben estar disponibles.']);
        }

        $lines = [];

        // Fixed dozen pricing: every 12 units of one flavor sell at its dozen price; the rest at its
        // stored unit price. There is no combined dozen and no cap: 11 units may cost more than a dozen.
        foreach ($quantities as $productId => $quantity) {
            $product = $products[$productId];
            if ($product->pricingFamily === 'empanada') {
                $dozens = intdiv($quantity, 12);
                if ($dozens > 0) {
                    $lines[] = $this->productLine($product, 'dozen', $dozens, 12, $product->dozenPrice, $product->cookedDozenPrice);
                }
                $quantity %= 12;
            }
            if ($quantity > 0) {
                $lines[] = $this->productLine($product, 'unit', $quantity, 1, $product->unitPrice, $product->cookedUnitPrice);
            }
        }

        $pricedAddons = $this->priceAddons($addons);
        $productsCookedSubtotal = array_sum(array_map(fn (PricingLine $line): int => $line->cookedLineTotal, $lines));
        $rawDiscount = array_sum(array_map(fn (PricingLine $line): int => $line->rawDiscount, $lines));
        $productsSubtotal = array_sum(array_map(fn (PricingLine $line): int => $line->lineTotal, $lines));
        $addonsSubtotal = array_sum(array_map(fn (PricingAddon $addon): int => $addon->lineTotal, $pricedAddons));
        $shippingFee = $deliveryType === DeliveryType::Delivery
            ? OrderSetting::integer(OrderSettingKey::DeliveryFee)
            : 0;
        $discountableSubtotal = $productsSubtotal + $addonsSubtotal;
        // Validates the discount pair with user-facing messages; the amount itself is exact below.
        $this->discountAmount($discountType, $discountValue, $discountableSubtotal);
        // Line prices are whole pesos; the only rounding is the final total, once, to ARS 100.
        $totals = ExactOrderTotals::calculate($productsSubtotal * 12, $addonsSubtotal, $shippingFee, $discountType, $discountValue);

        return new OrderPriceQuote(
            $condition, $deliveryType, $lines, $pricedAddons,
            $productsCookedSubtotal, $rawDiscount,
            $productsSubtotal, $addonsSubtotal, $shippingFee, 0,
            $discountableSubtotal, $discountType, $discountValue, $totals->discountAmount,
            $totals->total,
            roundingAdjustment: $totals->roundingAdjustment,
            discountExactNumerator1200: $totals->discountNumerator1200,
            netPreRoundExactNumerator1200: $totals->netNumerator1200,
            roundingAdjustmentNumerator1200: $totals->roundingAdjustmentNumerator1200,
        );
    }

    private function productLine(
        SellableProductPrice $product,
        string $mode,
        int $quantity,
        int $units,
        int $price,
        int $cookedPrice,
    ): PricingLine {
        return new PricingLine(
            pricingMode: $mode, quantity: $quantity, unitsCount: $quantity * $units,
            unitPrice: $price, lineTotal: $quantity * $price,
            cookedUnitPrice: $cookedPrice, cookedLineTotal: $quantity * $cookedPrice,
            rawDiscount: $quantity * ($cookedPrice - $price),
            regularPriceTotal: $quantity * $price, savings: 0,
            productId: $product->id, productName: $product->name,
        );
    }

    private function discountAmount(?DiscountType $type, ?int $value, int $discountableSubtotal): int
    {
        if ($type === null && $value === null) {
            return 0;
        }
        if ($type === null || $value === null || $value < 0) {
            throw ValidationException::withMessages(['discount' => 'El tipo y el valor del descuento deben informarse juntos con un valor no negativo.']);
        }
        if ($type === DiscountType::Percentage && $value > 100) {
            throw ValidationException::withMessages(['discount_value' => 'Los descuentos porcentuales no pueden superar 100.']);
        }

        $amount = $type === DiscountType::Percentage
            ? intdiv(($discountableSubtotal * $value) + 50, 100)
            : $value;

        return min($amount, $discountableSubtotal);
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
                throw ValidationException::withMessages(['addons' => 'Los adicionales no pueden repetirse y sus cantidades deben estar entre 1 y 100.']);
            }
            $seen[$input['addon_id']] = true;
            $addon = OrderAddon::query()->whereKey($input['addon_id'])->where('is_available', true)->first();
            if (! $addon) {
                throw ValidationException::withMessages(['addons' => 'Todos los adicionales deben estar disponibles.']);
            }
            $priced[] = new PricingAddon($addon->id, $addon->name, $input['quantity'], $addon->price, $input['quantity'] * $addon->price);
        }

        return $priced;
    }
}
