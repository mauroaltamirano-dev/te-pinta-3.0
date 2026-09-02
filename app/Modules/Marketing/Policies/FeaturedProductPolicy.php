<?php

namespace App\Modules\Marketing\Policies;

use App\Modules\Identity\Models\User;
use App\Modules\Marketing\Models\FeaturedProduct;

final class FeaturedProductPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->is_admin;
    }

    public function create(User $user): bool
    {
        return $user->is_admin;
    }

    public function update(User $user, FeaturedProduct $featuredProduct): bool
    {
        return $user->is_admin;
    }
}
