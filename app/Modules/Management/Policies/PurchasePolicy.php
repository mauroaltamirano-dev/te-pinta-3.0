<?php

namespace App\Modules\Management\Policies;

use App\Modules\Identity\Enums\Permission;
use App\Modules\Identity\Models\User;
use App\Modules\Management\Models\Purchase;

final class PurchasePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission(Permission::FinanceView);
    }

    public function view(User $user, Purchase $purchase): bool
    {
        return $user->hasPermission(Permission::FinanceView);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission(Permission::FinanceManage);
    }

    public function reverse(User $user, Purchase $purchase): bool
    {
        return $user->hasPermission(Permission::FinanceManage);
    }
}
