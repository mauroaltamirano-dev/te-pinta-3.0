<?php

namespace App\Modules\Customers\Actions;

use App\Modules\Customers\Models\Customer;
use Illuminate\Support\Facades\Log;

final class DeleteCustomer
{
    public function execute(Customer $customer, int $adminId): void
    {
        $customer->delete();

        Log::info('[Customers] customer.deleted', [
            'module' => 'Customers',
            'customer_id' => $customer->id,
            'admin_id' => $adminId,
        ]);
    }
}
