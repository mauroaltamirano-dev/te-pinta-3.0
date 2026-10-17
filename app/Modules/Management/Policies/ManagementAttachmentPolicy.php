<?php

namespace App\Modules\Management\Policies;

use App\Modules\Identity\Enums\Permission;
use App\Modules\Identity\Models\User;
use App\Modules\Management\Models\FinancialTransaction;
use App\Modules\Management\Models\ManagementAttachment;

final class ManagementAttachmentPolicy
{
    public function viewAny(User $user, FinancialTransaction $transaction): bool
    {
        return $user->hasPermission(Permission::FinanceView);
    }

    public function create(User $user, FinancialTransaction $transaction): bool
    {
        return $user->hasPermission(Permission::FinanceManage);
    }

    public function view(User $user, ManagementAttachment $attachment): bool
    {
        return $user->hasPermission(Permission::FinanceView);
    }

    public function delete(User $user, ManagementAttachment $attachment): bool
    {
        return $user->hasPermission(Permission::FinanceManage);
    }
}
