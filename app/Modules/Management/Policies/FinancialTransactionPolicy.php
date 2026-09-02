<?php

namespace App\Modules\Management\Policies;

use App\Modules\Identity\Models\User;
use App\Modules\Management\Models\FinancialTransaction;

final class FinancialTransactionPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->is_admin;
    }

    public function create(User $user): bool
    {
        return $user->is_admin;
    }

    public function reverse(User $user, FinancialTransaction $transaction): bool
    {
        return $user->is_admin;
    }
}
