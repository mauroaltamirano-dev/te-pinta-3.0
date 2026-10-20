<?php

namespace App\Modules\Orders\Support;

use App\Modules\Orders\Enums\TableSessionStatus;
use App\Modules\Orders\Models\TableSession;
use Illuminate\Validation\ValidationException;

/** An open table's order changes only through table service (rounds, bill, charge, cancel). */
final class OpenTableOrders
{
    public function assertNotOpen(int $orderId): void
    {
        if (TableSession::query()->where('order_id', $orderId)->where('status', TableSessionStatus::Open)->exists()) {
            throw ValidationException::withMessages(['order' => 'Este pedido pertenece a una mesa abierta; gestionalo desde Mesas.']);
        }
    }
}
