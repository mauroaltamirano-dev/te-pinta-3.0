<?php

namespace App\Modules\Orders\Http\Controllers;

use App\Modules\Orders\Actions\UpdateOrderStatus;
use App\Modules\Orders\Enums\OrderStatus;
use App\Modules\Orders\Http\Requests\UpdateOrderStatusRequest;
use App\Modules\Orders\Models\Order;
use Illuminate\Http\RedirectResponse;

final class OrderStatusController
{
    public function __invoke(UpdateOrderStatusRequest $request, Order $order, UpdateOrderStatus $update): RedirectResponse
    {
        $update->execute($order, OrderStatus::from($request->validated('status')), (int) $request->user()->getAuthIdentifier());

        return back()->with('success', 'Estado del pedido actualizado.');
    }
}
