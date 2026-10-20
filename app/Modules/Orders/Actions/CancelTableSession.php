<?php

namespace App\Modules\Orders\Actions;

use App\Modules\Identity\Models\User;
use App\Modules\Orders\Enums\OrderStatus;
use App\Modules\Orders\Enums\TableSessionStatus;
use App\Modules\Orders\Models\Order;
use App\Modules\Orders\Models\TableSession;
use App\Modules\Orders\Support\OrderBusinessDate;
use App\Modules\Orders\Support\OrderPhysicalFulfillment;
use App\Modules\Orders\Support\TableSessionGuard;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/** Cancels an unpaid open table: its order stays in history as cancelled and the table is free again. */
final readonly class CancelTableSession
{
    public function __construct(private TableSessionGuard $guard, private OrderPhysicalFulfillment $fulfillment, private OrderBusinessDate $businessDate) {}

    public function execute(TableSession $session, User $actor): TableSession
    {
        $cancelled = DB::transaction(function () use ($session, $actor): TableSession {
            $locked = $this->guard->lockOpen($session, $actor, cashierOnly: true);
            $order = Order::query()->lockForUpdate()->findOrFail($locked->order_id);
            $previous = $order->status;
            $this->fulfillment->assertTransition($order->id, $previous, OrderStatus::Cancelled);
            $order->update(['status' => OrderStatus::Cancelled, 'edit_version' => $order->edit_version + 1]);
            $this->fulfillment->dispatch($order->id, $previous, OrderStatus::Cancelled, $this->businessDate->today(), $actor->id);
            $locked->update(['status' => TableSessionStatus::Cancelled, 'closed_at' => now(), 'closed_by' => $actor->id]);

            return $locked;
        });
        Log::info('[Orders] table_session.cancelled', ['module' => 'Orders', 'table_session_id' => $cancelled->id, 'actor_id' => $actor->id]);

        return $cancelled;
    }
}
