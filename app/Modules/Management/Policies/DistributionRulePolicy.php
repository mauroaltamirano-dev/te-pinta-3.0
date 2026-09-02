<?php

namespace App\Modules\Management\Policies;

use App\Modules\Identity\Models\User;
use App\Modules\Management\Models\DistributionRule;

final class DistributionRulePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->is_admin;
    }

    public function create(User $user): bool
    {
        return $user->is_admin;
    }

    public function createVersion(User $user, DistributionRule $rule): bool
    {
        return $user->is_admin;
    }

    public function update(User $user, DistributionRule $rule): bool
    {
        return $user->is_admin;
    }
}
