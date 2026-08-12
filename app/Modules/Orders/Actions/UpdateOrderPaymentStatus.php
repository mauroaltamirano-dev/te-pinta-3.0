<?php

namespace App\Modules\Orders\Actions;

use App\Modules\Orders\Enums\PaymentStatus;
use App\Modules\Orders\Models\Order;
use Illuminate\Support\Facades\Log;

final class UpdateOrderPaymentStatus
{
    public function execute(Order $order, PaymentStatus $status, int $adminId): Order
    {
        $previous = $order->payment_status;
        $order->update(['payment_status' => $status]);
        Log::info('[Orders] order.payment_status_changed', [
            'module' => 'Orders', 'order_id' => $order->public_id, 'admin_id' => $adminId,
            'from' => $previous->value, 'to' => $status->value,
        ]);

        return $order->refresh();
    }
}
