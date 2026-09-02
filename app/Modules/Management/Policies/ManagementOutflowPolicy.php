<?php

namespace App\Modules\Management\Policies;

use App\Modules\Identity\Models\User;

final class ManagementOutflowPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->is_admin;
    }

    public function create(User $user): bool
    {
        return $user->is_admin;
    }
}
