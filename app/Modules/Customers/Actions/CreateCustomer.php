<?php

namespace App\Modules\Customers\Actions;

use App\Modules\Customers\Data\CustomerData;
use App\Modules\Customers\Models\Customer;
use Illuminate\Support\Facades\Log;

final class CreateCustomer
{
    public function execute(CustomerData $data, ?int $adminId = null): Customer
    {
        $customer = Customer::query()->create($data->toArray());

        Log::info('[Customers] customer.created', [
            'module' => 'Customers',
            'customer_id' => $customer->id,
            'admin_id' => $adminId,
        ]);

        return $customer;
    }
}
