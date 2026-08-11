<?php

namespace App\Modules\Products\Policies;

use App\Modules\Identity\Models\User;
use App\Modules\Products\Models\Category;

final class CategoryPolicy
{
    public function create(User $user): bool
    {
        return $user->is_admin;
    }

    public function update(User $user, Category $category): bool
    {
        return $user->is_admin;
    }
}
