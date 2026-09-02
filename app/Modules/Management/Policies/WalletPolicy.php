<?php

namespace App\Modules\Management\Policies;

use App\Modules\Identity\Models\User;
use App\Modules\Management\Models\Wallet;

final class WalletPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->is_admin;
    }

    public function create(User $user): bool
    {
        return $user->is_admin;
    }

    public function update(User $user, Wallet $wallet): bool
    {
        return $user->is_admin;
    }

    public function archive(User $user, Wallet $wallet): bool
    {
        return $user->is_admin;
    }

    public function restore(User $user, Wallet $wallet): bool
    {
        return $user->is_admin;
    }
}
