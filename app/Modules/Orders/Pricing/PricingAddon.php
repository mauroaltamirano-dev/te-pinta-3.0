<?php

namespace App\Modules\Orders\Pricing;

final readonly class PricingAddon
{
    public function __construct(
        public int $addonId,
        public string $name,
        public int $quantity,
        public int $unitPrice,
        public int $lineTotal,
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return get_object_vars($this);
    }
}
