<?php

namespace App\Modules\Marketing\Policies;

use App\Modules\Identity\Enums\Permission;
use App\Modules\Identity\Models\User;
use App\Modules\Marketing\Models\FeaturedProduct;

final class FeaturedProductPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission(Permission::MarketingManage);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission(Permission::MarketingManage);
    }

    public function update(User $user, FeaturedProduct $featuredProduct): bool
    {
        return $user->hasPermission(Permission::MarketingManage);
    }
}
