<?php

namespace App\Modules\Orders\Http\Controllers;

use App\Modules\Orders\Actions\UpdateOrderPaymentStatus;
use App\Modules\Orders\Enums\PaymentStatus;
use App\Modules\Orders\Http\Requests\UpdateOrderPaymentStatusRequest;
use App\Modules\Orders\Models\Order;
use Illuminate\Http\RedirectResponse;

final class OrderPaymentStatusController
{
    public function __invoke(UpdateOrderPaymentStatusRequest $request, Order $order, UpdateOrderPaymentStatus $update): RedirectResponse
    {
        $update->execute($order, PaymentStatus::from($request->validated('payment_status')), (int) $request->user()->getAuthIdentifier());

        return back()->with('success', 'Payment status updated.');
    }
}
