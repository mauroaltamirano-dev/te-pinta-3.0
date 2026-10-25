<?php

namespace App\Modules\Orders\Http\Controllers;

use App\Modules\Orders\Enums\OrderSource;
use App\Modules\Orders\Enums\OrderStatus;
use App\Modules\Orders\Models\Order;
use Illuminate\Http\JsonResponse;

/** Polled by every admin page so staff notice web orders waiting for confirmation. */
final class WebOrderAlertController
{
    public function __invoke(): JsonResponse
    {
        $pending = Order::query()
            ->where('source', OrderSource::PublicCheckout)
            ->where('status', OrderStatus::PendingConfirmation);
        $latest = (clone $pending)->latest('id')->first(['id', 'public_id', 'customer_name_snapshot']);

        return response()->json(['data' => [
            'pending_count' => $pending->count(),
            'latest' => $latest === null ? null : [
                'id' => $latest->id,
                'public_id' => $latest->public_id,
                'customer_name' => $latest->customer_name_snapshot,
                'url' => route('admin.orders.show', $latest),
            ],
        ]]);
    }
}
