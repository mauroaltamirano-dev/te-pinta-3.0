<?php

namespace App\Modules\Management\Policies;

use App\Modules\Identity\Models\User;
use App\Modules\Management\Models\Purchase;

final class PurchasePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->is_admin;
    }

    public function view(User $user, Purchase $purchase): bool
    {
        return $user->is_admin;
    }

    public function create(User $user): bool
    {
        return $user->is_admin;
    }

    public function reverse(User $user, Purchase $purchase): bool
    {
        return $user->is_admin;
    }
}
