<?php

namespace App\Modules\Customers\Policies;

use App\Modules\Customers\Models\Customer;
use App\Modules\Identity\Models\User;

final class CustomerPolicy
{
    public function view(User $user, Customer $customer): bool
    {
        return $user->is_admin;
    }
}
