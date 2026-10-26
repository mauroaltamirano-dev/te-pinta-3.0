<?php

namespace App\Modules\Orders\Actions;

use App\Modules\Orders\Enums\DeliveryType;
use App\Modules\Orders\Enums\OrderStatus;
use App\Modules\Orders\Models\Order;
use App\Modules\Orders\Support\OpenTableOrders;
use App\Modules\Orders\Support\OrderBusinessDate;
use App\Modules\Orders\Support\OrderDocuments;
use App\Modules\Orders\Support\OrderPhysicalFulfillment;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

final readonly class UpdateOrderStatus
{
    public function __construct(private OrderBusinessDate $businessDate, private OrderPhysicalFulfillment $fulfillment, private OpenTableOrders $openTables, private OrderDocuments $documents) {}

    public function execute(Order $order, OrderStatus $status, int $adminId): Order
    {
        return DB::transaction(function () use ($order, $status, $adminId): Order {
            $locked = Order::query()->lockForUpdate()->findOrFail($order->id);
            $this->openTables->assertNotOpen($locked->id);
            $previous = $locked->status;
            $this->fulfillment->assertTransition($locked->id, $previous, $status);
            // A quick order is confirmed (or reopened after cancelling) only once staff completed its customer and schedule.
            if ($locked->delivery_type !== DeliveryType::DineIn && ! in_array($status, [OrderStatus::PendingConfirmation, OrderStatus::Cancelled], true)
                && ($locked->customer_name_snapshot === null || $locked->delivery_period === null)) {
                throw ValidationException::withMessages(['status' => 'Completá el nombre del cliente y la franja horaria antes de confirmar el pedido.']);
            }
            if ($previous !== $status) {
                $locked->update(['status' => $status, 'edit_version' => $locked->edit_version + 1]);
                $this->fulfillment->dispatch($locked->id, $previous, $status, $this->businessDate->today(), $adminId);
                if ($previous === OrderStatus::PendingConfirmation && $status !== OrderStatus::Cancelled) {
                    event($this->documents->orderKitchenTicket($locked, $adminId));
                }
            }
            Log::info($status === OrderStatus::Cancelled ? '[Orders] order.cancelled' : '[Orders] order.status_changed', ['module' => 'Orders', 'order_id' => $locked->public_id, 'admin_id' => $adminId, 'from' => $previous->value, 'to' => $status->value]);

            return $order->refresh();
        });
    }
}
