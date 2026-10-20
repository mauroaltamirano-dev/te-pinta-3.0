<?php

namespace App\Modules\Orders\Actions;

use App\Modules\Identity\Models\User;
use App\Modules\Orders\Enums\OrderStatus;
use App\Modules\Orders\Enums\PaymentMethod;
use App\Modules\Orders\Enums\PaymentStatus;
use App\Modules\Orders\Enums\TableSessionStatus;
use App\Modules\Orders\Events\OrderPaymentConfirmed;
use App\Modules\Orders\Models\Order;
use App\Modules\Orders\Models\TableSession;
use App\Modules\Orders\Support\OrderBusinessDate;
use App\Modules\Orders\Support\OrderPhysicalFulfillment;
use App\Modules\Orders\Support\TableSessionGuard;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Charges an open table after the cash or transfer was checked: paid with the method and delivered,
 * atomically. Payment (Management) and consumption (Inventory) listeners run in the same transaction,
 * so any failure leaves the table open and unpaid. The closed session freezes its order.
 */
final readonly class ChargeTableSession
{
    public function __construct(
        private TableSessionGuard $guard,
        private ResolveOrderPayment $payments,
        private OrderPhysicalFulfillment $fulfillment,
        private OrderBusinessDate $businessDate,
    ) {}

    public function execute(TableSession $session, PaymentMethod $method, User $actor): TableSession
    {
        $closed = DB::transaction(function () use ($session, $method, $actor): TableSession {
            $locked = $this->guard->lockOpen($session, $actor, cashierOnly: true);
            $order = Order::query()->lockForUpdate()->findOrFail($locked->order_id);
            $payment = $this->payments->forTotal((int) $order->total, PaymentStatus::Paid, $method);
            $this->fulfillment->assertTransition($order->id, $order->status, OrderStatus::Delivered);
            $previous = $order->status;
            $order->update([
                'payment_status' => $payment['status'], 'payment_method' => $payment['method'],
                'status' => OrderStatus::Delivered, 'edit_version' => $order->edit_version + 1,
            ]);
            $today = $this->businessDate->today();
            if ($payment['status'] === PaymentStatus::Paid) {
                event(new OrderPaymentConfirmed(orderId: (int) $order->id, amount: (int) $order->total, confirmedOn: $today, adminId: $actor->id));
            }
            $this->fulfillment->dispatch($order->id, $previous, OrderStatus::Delivered, $today, $actor->id);
            $locked->update(['status' => TableSessionStatus::Closed, 'closed_at' => now(), 'closed_by' => $actor->id]);

            return $locked;
        });
        Log::info('[Orders] table_session.charged', ['module' => 'Orders', 'table_session_id' => $closed->id, 'actor_id' => $actor->id, 'method' => $method->value]);

        return $closed;
    }
}
