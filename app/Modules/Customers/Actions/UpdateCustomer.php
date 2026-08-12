<?php

namespace App\Modules\Customers\Actions;

use App\Modules\Customers\Data\CustomerData;
use App\Modules\Customers\Models\Customer;
use Illuminate\Support\Facades\Log;

final class UpdateCustomer
{
    public function execute(Customer $customer, CustomerData $data, int $adminId): Customer
    {
        $customer->update($data->toArray());

        Log::info('[Customers] customer.updated', [
            'module' => 'Customers',
            'customer_id' => $customer->id,
            'admin_id' => $adminId,
        ]);

        return $customer->refresh();
    }
}
