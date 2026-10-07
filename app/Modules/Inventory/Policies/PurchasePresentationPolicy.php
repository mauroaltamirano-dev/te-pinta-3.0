<?php

namespace App\Modules\Inventory\Policies;

use App\Modules\Identity\Models\User;
use App\Modules\Inventory\Models\PurchasePresentation;

final class PurchasePresentationPolicy
{
    public function create(User $user): bool
    {
        return $user->is_admin;
    }

    public function update(User $user, PurchasePresentation $presentation): bool
    {
        return $user->is_admin;
    }
}
