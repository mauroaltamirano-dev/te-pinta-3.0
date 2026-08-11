<?php

namespace App\Modules\Orders\Policies;

use App\Modules\Identity\Models\User;
use App\Modules\Orders\Models\Order;

final class OrderPolicy
{
    public function view(User $user, Order $order): bool
    {
        return $user->is_admin;
    }

    public function update(User $user, Order $order): bool
    {
        return $user->is_admin;
    }
}
