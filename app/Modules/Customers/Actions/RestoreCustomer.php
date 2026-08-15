<?php

namespace App\Modules\Customers\Actions;

use App\Modules\Customers\Models\Customer;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class RestoreCustomer
{
    public function execute(Customer $customer, int $adminId): void
    {
        if (! $customer->trashed()) {
            throw new NotFoundHttpException;
        }

        if ($customer->phone_normalized !== null && Customer::query()
            ->where('phone_normalized', $customer->phone_normalized)
            ->exists()) {
            throw ValidationException::withMessages([
                'restore' => 'No se puede restaurar el cliente porque su teléfono está asignado a otro cliente activo. Modificá ese cliente y volvé a intentarlo.',
            ]);
        }

        $customer->restore();

        Log::info('[Customers] customer.restored', [
            'module' => 'Customers',
            'customer_id' => $customer->id,
            'admin_id' => $adminId,
        ]);
    }
}
