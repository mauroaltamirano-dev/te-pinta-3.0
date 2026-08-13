<?php

namespace App\Modules\Orders\Actions;

use App\Modules\Customers\Models\Customer;
use App\Modules\Orders\Data\OrderCheckoutData;
use App\Modules\Orders\Enums\OrderSource;

final class ResolveOrderCustomer
{
    public function execute(OrderCheckoutData $data, OrderSource $source): Customer
    {
        if ($source === OrderSource::Admin && $data->customerId !== null) {
            return Customer::query()->findOrFail($data->customerId);
        }

        if ($source === OrderSource::PublicCheckout && $data->customerPhoneNormalized !== null) {
            $existing = Customer::query()->where('phone_normalized', $data->customerPhoneNormalized)->first();
            if ($existing) {
                return $existing;
            }
        }

        $attributes = [
            'name' => $data->customerName,
            'phone' => $data->customerPhone,
            'phone_normalized' => $data->customerPhoneNormalized,
            'address' => $data->customerAddress,
            'notes' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ];

        if ($source === OrderSource::Admin) {
            return Customer::query()->create($attributes);
        }

        Customer::query()->insertOrIgnore($attributes);

        return Customer::query()
            ->when($data->customerPhoneNormalized !== null, fn ($query) => $query->where('phone_normalized', $data->customerPhoneNormalized))
            ->firstOrFail();
    }
}
