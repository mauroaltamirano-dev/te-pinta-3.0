<?php

namespace App\Modules\Management\Policies;

use App\Modules\Identity\Enums\Permission;
use App\Modules\Identity\Models\User;
use App\Modules\Management\Models\FinancialTransaction;

final class FinancialTransactionPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission(Permission::FinanceView);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission(Permission::FinanceManage);
    }

    public function reverse(User $user, FinancialTransaction $transaction): bool
    {
        return $user->hasPermission(Permission::FinanceManage);
    }
}
