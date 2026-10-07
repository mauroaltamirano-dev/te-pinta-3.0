<?php

namespace App\Modules\Inventory\Policies;

use App\Modules\Identity\Models\User;
use App\Modules\Inventory\Models\InventoryItem;

final class InventoryItemPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->is_admin;
    }

    public function create(User $user): bool
    {
        return $user->is_admin;
    }

    public function update(User $user, InventoryItem $item): bool
    {
        return $user->is_admin;
    }
}
