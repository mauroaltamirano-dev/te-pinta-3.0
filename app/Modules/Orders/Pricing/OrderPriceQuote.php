<?php

namespace App\Modules\Orders\Pricing;

use App\Modules\Orders\Enums\DeliveryType;
use App\Modules\Orders\Enums\DiscountType;
use App\Modules\Orders\Enums\OrderCondition;

final readonly class OrderPriceQuote
{
    /**
     * @param  array<int, PricingLine>  $lines
     * @param  array<int, PricingAddon>  $addons
     */
    public function __construct(
        public OrderCondition $condition,
        public DeliveryType $deliveryType,
        public array $lines,
        public array $addons,
        public int $productsCookedSubtotal,
        public int $rawDiscount,
        public int $productsSubtotal,
        public int $addonsSubtotal,
        public int $shippingFee,
        public int $savings,
        public int $discountableSubtotal,
        public ?DiscountType $discountType,
        public ?int $discountValue,
        public int $discountAmount,
        public int $total,
        public int $roundingAdjustment = 0,
        public int $discountExactNumerator1200 = 0,
        public int $netPreRoundExactNumerator1200 = 0,
        public int $roundingAdjustmentNumerator1200 = 0,
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'condition' => $this->condition->value,
            'delivery_type' => $this->deliveryType->value,
            'lines' => array_map(fn (PricingLine $line): array => $line->toArray(), $this->lines),
            'addons' => array_map(fn (PricingAddon $addon): array => $addon->toArray(), $this->addons),
            'products_cooked_subtotal' => $this->productsCookedSubtotal,
            'raw_discount' => $this->rawDiscount,
            'products_subtotal' => $this->productsSubtotal,
            'addons_subtotal' => $this->addonsSubtotal,
            'shipping_fee' => $this->shippingFee,
            'savings' => $this->savings,
            'discountable_subtotal' => $this->discountableSubtotal,
            'discount_type' => $this->discountType?->value,
            'discount_value' => $this->discountValue,
            'discount_amount' => $this->discountAmount,
            'rounding_adjustment' => $this->roundingAdjustment,
            'total' => $this->total,
        ];
    }
}
