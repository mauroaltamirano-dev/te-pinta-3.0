<?php

namespace App\Core\Data;

final readonly class SellableProductPrice
{
    public function __construct(
        public int $id,
        public string $slug,
        public string $name,
        public string $pricingFamily,
        public int $unitPrice,
        public int $halfDozenPrice,
        public int $dozenPrice,
        public int $cookedUnitPrice,
        public int $cookedHalfDozenPrice,
        public int $cookedDozenPrice,
    ) {}
}
