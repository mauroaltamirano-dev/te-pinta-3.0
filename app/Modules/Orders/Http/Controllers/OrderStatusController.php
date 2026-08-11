<?php

namespace App\Modules\Orders\Http\Controllers;

use App\Modules\Orders\Enums\OrderStatus;
use App\Modules\Orders\Http\Requests\UpdateOrderStatusRequest;
use App\Modules\Orders\Models\Order;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Log;

final class OrderStatusController
{
    public function __invoke(UpdateOrderStatusRequest $request, Order $order): RedirectResponse
    {
        $previous = $order->status;
        $order->status = OrderStatus::from($request->validated('status'));
        $order->save();

        Log::info('order.status_changed', [
            'module' => 'Orders',
            'order_id' => $order->public_id,
            'admin_id' => $request->user()->getAuthIdentifier(),
            'from' => $previous->value,
            'to' => $order->status->value,
        ]);

        return back();
    }
}
