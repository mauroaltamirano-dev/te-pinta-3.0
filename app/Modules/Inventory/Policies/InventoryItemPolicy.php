<?php

namespace App\Modules\Inventory\Policies;

use App\Modules\Identity\Enums\Permission;
use App\Modules\Identity\Models\User;
use App\Modules\Inventory\Models\InventoryItem;

final class InventoryItemPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission(Permission::InventoryView);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission(Permission::InventoryManage);
    }

    public function update(User $user, InventoryItem $item): bool
    {
        return $user->hasPermission(Permission::InventoryManage);
    }
}
