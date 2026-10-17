<?php

namespace App\Modules\Management\Policies;

use App\Modules\Identity\Enums\Permission;
use App\Modules\Identity\Models\User;
use App\Modules\Management\Models\DistributionRule;

final class DistributionRulePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission(Permission::FinanceView);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission(Permission::FinanceManage);
    }

    public function createVersion(User $user, DistributionRule $rule): bool
    {
        return $user->hasPermission(Permission::FinanceManage);
    }

    public function update(User $user, DistributionRule $rule): bool
    {
        return $user->hasPermission(Permission::FinanceManage);
    }
}
