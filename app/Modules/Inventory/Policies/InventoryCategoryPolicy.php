<?php

namespace App\Modules\Inventory\Policies;

use App\Modules\Identity\Models\User;
use App\Modules\Inventory\Models\InventoryCategory;

final class InventoryCategoryPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->is_admin;
    }

    public function create(User $user): bool
    {
        return $user->is_admin;
    }

    public function update(User $user, InventoryCategory $category): bool
    {
        return $user->is_admin;
    }
}
