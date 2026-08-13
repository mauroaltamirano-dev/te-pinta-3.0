<?php

namespace App\Modules\Orders\Actions;

use App\Modules\Orders\Enums\OrderStatus;
use App\Modules\Orders\Models\Order;
use Illuminate\Support\Facades\Log;

final class UpdateOrderStatus
{
    public function execute(Order $order, OrderStatus $status, int $adminId): Order
    {
        $previous = $order->status;
        $order->update(['status' => $status, 'edit_version' => $order->edit_version + 1]);
        Log::info($status === OrderStatus::Cancelled ? '[Orders] order.cancelled' : '[Orders] order.status_changed', [
            'module' => 'Orders', 'order_id' => $order->public_id, 'admin_id' => $adminId,
            'from' => $previous->value, 'to' => $status->value,
        ]);

        return $order->refresh();
    }
}
