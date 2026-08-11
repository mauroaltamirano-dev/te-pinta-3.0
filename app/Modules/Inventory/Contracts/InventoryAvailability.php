<?php

namespace App\Modules\Inventory\Contracts;

interface InventoryAvailability
{
    public function isAvailable(int $productId, int $quantity): bool;
}
