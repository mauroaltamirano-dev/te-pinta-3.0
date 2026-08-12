<?php

namespace App\Modules\Orders\Pricing;

use App\Modules\Orders\Enums\DeliveryType;
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
        public int $productsSubtotal,
        public int $addonsSubtotal,
        public int $shippingFee,
        public int $savings,
        public int $total,
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'condition' => $this->condition->value,
            'delivery_type' => $this->deliveryType->value,
            'lines' => array_map(fn (PricingLine $line): array => $line->toArray(), $this->lines),
            'addons' => array_map(fn (PricingAddon $addon): array => $addon->toArray(), $this->addons),
            'products_subtotal' => $this->productsSubtotal,
            'addons_subtotal' => $this->addonsSubtotal,
            'shipping_fee' => $this->shippingFee,
            'savings' => $this->savings,
            'total' => $this->total,
        ];
    }
}
