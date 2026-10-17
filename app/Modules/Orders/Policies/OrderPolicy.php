<?php

namespace App\Modules\Orders\Policies;

use App\Modules\Identity\Enums\Permission;
use App\Modules\Identity\Models\User;
use App\Modules\Orders\Models\Order;

final class OrderPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission(Permission::OrdersView);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission(Permission::OrdersManage);
    }

    public function view(User $user, Order $order): bool
    {
        return $user->hasPermission(Permission::OrdersView);
    }

    public function update(User $user, Order $order): bool
    {
        return $user->hasPermission(Permission::OrdersManage);
    }

    public function delete(User $user, Order $order): bool
    {
        return $user->hasPermission(Permission::OrdersManage);
    }

    public function restore(User $user, Order $order): bool
    {
        return $user->hasPermission(Permission::OrdersManage);
    }
}
