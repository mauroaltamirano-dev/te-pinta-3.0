<?php

namespace App\Modules\Products\Policies;

use App\Modules\Identity\Enums\Permission;
use App\Modules\Identity\Models\User;
use App\Modules\Products\Models\Product;

final class ProductPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission(Permission::CatalogView);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission(Permission::CatalogManage);
    }

    public function update(User $user, Product $product): bool
    {
        return $user->hasPermission(Permission::CatalogManage);
    }

    public function delete(User $user, Product $product): bool
    {
        return $user->hasPermission(Permission::CatalogManage);
    }
}
