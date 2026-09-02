<?php

namespace App\Modules\Orders\Pricing;

final readonly class PricingLine
{
    /** @param array<int, array{product_id: int, product_name: string, quantity: int}> $composition */
    public function __construct(
        public string $pricingMode,
        public int $quantity,
        public int $unitsCount,
        public int $unitPrice,
        public int $lineTotal,
        public int $cookedUnitPrice,
        public int $cookedLineTotal,
        public int $rawDiscount,
        public int $regularPriceTotal,
        public int $savings,
        public ?int $productId = null,
        public ?string $productName = null,
        public array $composition = [],
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return get_object_vars($this);
    }
}
