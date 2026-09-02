<?php

namespace App\Modules\Management\Policies;

use App\Modules\Identity\Models\User;
use App\Modules\Management\Models\FinancialTransaction;
use App\Modules\Management\Models\ManagementAttachment;

final class ManagementAttachmentPolicy
{
    public function viewAny(User $user, FinancialTransaction $transaction): bool
    {
        return $user->is_admin;
    }

    public function create(User $user, FinancialTransaction $transaction): bool
    {
        return $user->is_admin;
    }

    public function view(User $user, ManagementAttachment $attachment): bool
    {
        return $user->is_admin;
    }

    public function delete(User $user, ManagementAttachment $attachment): bool
    {
        return $user->is_admin;
    }
}
