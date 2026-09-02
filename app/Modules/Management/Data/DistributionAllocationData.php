<?php

namespace App\Modules\Management\Data;

final readonly class DistributionAllocationData
{
    public function __construct(
        public int $walletId,
        public int $percentageBps,
        public int $sortOrder,
    ) {}
}
