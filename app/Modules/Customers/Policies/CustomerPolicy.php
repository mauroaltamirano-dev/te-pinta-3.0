<?php

namespace App\Modules\Customers\Policies;

use App\Modules\Customers\Models\Customer;
use App\Modules\Identity\Models\User;

final class CustomerPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->is_admin;
    }

    public function view(User $user, Customer $customer): bool
    {
        return $user->is_admin;
    }

    public function create(User $user): bool
    {
        return $user->is_admin;
    }

    public function update(User $user, Customer $customer): bool
    {
        return $user->is_admin;
    }

    public function delete(User $user, Customer $customer): bool
    {
        return $user->is_admin;
    }

    public function restore(User $user, Customer $customer): bool
    {
        return $user->is_admin;
    }
}
