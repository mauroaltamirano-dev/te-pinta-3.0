<?php

namespace App\Modules\Orders\Http\Controllers;

use App\Modules\Orders\Http\Requests\EnableClosedOrderEditRequest;
use App\Modules\Orders\Models\Order;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

final class EnableClosedOrderEditController
{
    public function __invoke(EnableClosedOrderEditRequest $request, Order $order): RedirectResponse
    {
        if (! $order->status->isClosed()) {
            throw ValidationException::withMessages(['reason' => 'Este pedido no está cerrado y no necesita habilitación especial.']);
        }
        $version = (int) $request->validated('expected_version');
        if ((int) $order->edit_version !== $version) {
            throw ValidationException::withMessages(['expected_version' => 'El pedido fue modificado desde que lo abriste. Recargá antes de continuar.']);
        }
        $adminId = (int) $request->user()->getAuthIdentifier();
        $request->session()->put($this->key($order), [
            'admin_id' => $adminId,
            'version' => $version,
            'reason' => $request->validated('reason'),
        ]);

        Log::info('[Orders] closed_order_edit_enabled', [
            'module' => 'Orders',
            'order_id' => $order->public_id,
            'admin_id' => $adminId,
            'status' => $order->status->value,
            'version' => $version,
        ]);

        return to_route('admin.orders.edit', $order)->with('success', 'Edición del pedido cerrado habilitada.');
    }

    public static function sessionKey(Order $order): string
    {
        return 'orders.closed_edit_grants.'.$order->getKey();
    }

    private function key(Order $order): string
    {
        return self::sessionKey($order);
    }
}
