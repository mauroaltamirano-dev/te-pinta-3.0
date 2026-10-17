<?php

namespace App\Modules\Customers\Policies;

use App\Modules\Customers\Models\Customer;
use App\Modules\Identity\Enums\Permission;
use App\Modules\Identity\Models\User;

final class CustomerPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission(Permission::CustomersView);
    }

    public function view(User $user, Customer $customer): bool
    {
        return $user->hasPermission(Permission::CustomersView);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission(Permission::CustomersManage);
    }

    public function update(User $user, Customer $customer): bool
    {
        return $user->hasPermission(Permission::CustomersManage);
    }

    public function delete(User $user, Customer $customer): bool
    {
        return $user->hasPermission(Permission::CustomersManage);
    }

    public function restore(User $user, Customer $customer): bool
    {
        return $user->hasPermission(Permission::CustomersManage);
    }
}
