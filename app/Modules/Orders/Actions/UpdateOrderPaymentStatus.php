<?php

namespace App\Modules\Orders\Actions;

use App\Modules\Orders\Enums\PaymentMethod;
use App\Modules\Orders\Enums\PaymentStatus;
use App\Modules\Orders\Models\Order;
use Illuminate\Support\Facades\Log;

final class UpdateOrderPaymentStatus
{
    public function __construct(private readonly ResolveOrderPayment $payments) {}

    public function execute(Order $order, PaymentStatus $status, ?PaymentMethod $method, int $adminId): Order
    {
        $previous = $order->payment_status;
        $previousMethod = $order->payment_method;
        $payment = $this->payments->forTotal((int) $order->total, $status, $method);
        $order->update([
            'payment_status' => $payment['status'],
            'payment_method' => $payment['method'],
            'edit_version' => $order->edit_version + 1,
        ]);
        Log::info('[Orders] order.payment_status_changed', [
            'module' => 'Orders', 'order_id' => $order->public_id, 'admin_id' => $adminId,
            'from' => $previous->value, 'to' => $status->value,
            'method_from' => $previousMethod?->value, 'method_to' => $order->payment_method?->value,
        ]);

        return $order->refresh();
    }
}
