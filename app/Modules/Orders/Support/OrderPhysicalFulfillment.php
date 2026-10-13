<?php

namespace App\Modules\Orders\Support;

use App\Modules\Orders\Enums\OrderStatus;
use App\Modules\Orders\Events\OrderDelivered;
use App\Modules\Orders\Events\OrderDeliveryCancelled;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Current compatibility contract; financial refunds do not define future physical returns. */
final class OrderPhysicalFulfillment
{
    public function hasEvidence(int $orderId): bool
    {
        return DB::table('order_inventory_consumptions')->where('order_id', $orderId)->exists();
    }

    public function assertTransition(int $id, OrderStatus $previous, OrderStatus $next): void
    {
        if ($previous === OrderStatus::Delivered && ! in_array($next, [OrderStatus::Delivered, OrderStatus::Cancelled], true)) {
            throw ValidationException::withMessages(['status' => 'Delivered orders can only be cancelled.']);
        }
        if ($next === OrderStatus::Delivered && DB::table('order_inventory_consumption_reversals as r')->join('order_inventory_consumptions as c', 'c.id', '=', 'r.order_inventory_consumption_id')->where('c.order_id', $id)->exists()) {
            throw ValidationException::withMessages(['status' => 'A reversed delivery cannot be delivered again. Create a new order.']);
        }
    }

    public function dispatch(int $id, OrderStatus $previous, OrderStatus $next, CarbonImmutable $date, int $actor): void
    {
        if ($previous === $next) {
            return;
        }
        if ($next === OrderStatus::Delivered) {
            event(new OrderDelivered($id, $date, $actor));
        } elseif ($previous === OrderStatus::Delivered && $next === OrderStatus::Cancelled) {
            event(new OrderDeliveryCancelled($id, $date, $actor));
        }
    }
}
