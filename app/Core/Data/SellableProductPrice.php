<?php

namespace App\Core\Data;

final readonly class SellableProductPrice
{
    public function __construct(
        public int $id,
        public string $slug,
        public string $name,
        public int $unitPrice,
        public int $halfDozenPrice,
        public int $dozenPrice,
        public int $baseUnitPrice,
        public int $baseHalfDozenPrice,
        public int $baseDozenPrice,
    ) {}
}
