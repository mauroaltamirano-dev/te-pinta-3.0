<?php

namespace App\Modules\Products\Policies;

use App\Modules\Identity\Enums\Permission;
use App\Modules\Identity\Models\User;
use App\Modules\Products\Models\Category;

final class CategoryPolicy
{
    public function create(User $user): bool
    {
        return $user->hasPermission(Permission::CatalogManage);
    }

    public function update(User $user, Category $category): bool
    {
        return $user->hasPermission(Permission::CatalogManage);
    }
}
