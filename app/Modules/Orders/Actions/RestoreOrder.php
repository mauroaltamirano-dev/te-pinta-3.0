<?php

namespace App\Modules\Orders\Actions;

use App\Modules\Orders\Models\Order;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class RestoreOrder
{
    public function execute(Order $order, int $adminId): void
    {
        if (! $order->trashed()) {
            throw new NotFoundHttpException;
        }

        $order->restore();

        Log::info('[Orders] order.restored', [
            'module' => 'Orders',
            'order_id' => $order->public_id,
            'admin_id' => $adminId,
        ]);
    }
}
