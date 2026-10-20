<?php

namespace App\Modules\Orders\Actions;

use App\Modules\Orders\Enums\PaymentMethod;
use App\Modules\Orders\Enums\PaymentStatus;
use App\Modules\Orders\Events\OrderPaymentConfirmed;
use App\Modules\Orders\Models\Order;
use App\Modules\Orders\Support\OpenTableOrders;
use App\Modules\Orders\Support\OrderBusinessDate;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

final class UpdateOrderPaymentStatus
{
    public function __construct(
        private readonly ResolveOrderPayment $payments,
        private readonly OrderBusinessDate $businessDate,
        private readonly OpenTableOrders $openTables,
    ) {}

    public function execute(Order $order, PaymentStatus $status, ?PaymentMethod $method, int $adminId): Order
    {
        [$updated, $previous, $previousMethod] = DB::transaction(function () use ($order, $status, $method, $adminId): array {
            $locked = Order::query()->whereKey($order->getKey())->lockForUpdate()->firstOrFail();
            $this->openTables->assertNotOpen($locked->id);
            $previous = $locked->payment_status;
            $previousMethod = $locked->payment_method;
            $payment = $this->payments->forTotal((int) $locked->total, $status, $method);
            $locked->update([
                'payment_status' => $payment['status'],
                'payment_method' => $payment['method'],
                'edit_version' => $locked->edit_version + 1,
            ]);
            if ($previous !== PaymentStatus::Paid && $locked->payment_status === PaymentStatus::Paid) {
                event(new OrderPaymentConfirmed(
                    orderId: (int) $locked->id,
                    amount: (int) $locked->total,
                    confirmedOn: $this->businessDate->today(),
                    adminId: $adminId,
                ));
            }

            return [$locked->refresh(), $previous, $previousMethod];
        });
        Log::info('[Orders] order.payment_status_changed', [
            'module' => 'Orders', 'order_id' => $updated->public_id, 'admin_id' => $adminId,
            'from' => $previous->value, 'to' => $status->value,
            'method_from' => $previousMethod?->value, 'method_to' => $updated->payment_method?->value,
        ]);

        return $updated;
    }
}
