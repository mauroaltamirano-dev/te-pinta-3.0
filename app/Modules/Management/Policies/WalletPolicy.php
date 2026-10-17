<?php

namespace App\Modules\Management\Policies;

use App\Modules\Identity\Enums\Permission;
use App\Modules\Identity\Models\User;
use App\Modules\Management\Models\Wallet;

final class WalletPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission(Permission::FinanceView);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission(Permission::FinanceManage);
    }

    public function update(User $user, Wallet $wallet): bool
    {
        return $user->hasPermission(Permission::FinanceManage);
    }

    public function archive(User $user, Wallet $wallet): bool
    {
        return $user->hasPermission(Permission::FinanceManage);
    }

    public function restore(User $user, Wallet $wallet): bool
    {
        return $user->hasPermission(Permission::FinanceManage);
    }
}
