<?php

namespace App\Modules\Management\Policies;

use App\Modules\Identity\Enums\Permission;
use App\Modules\Identity\Models\User;

final class ManagementTransferPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission(Permission::FinanceView);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission(Permission::FinanceManage);
    }
}
