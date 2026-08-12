<?php

namespace App\Modules\Orders\Http\Controllers;

use App\Modules\Orders\Actions\CreateOrder;
use App\Modules\Orders\Actions\ResolvePublicCheckoutSelections;
use App\Modules\Orders\Data\OrderCheckoutData;
use App\Modules\Orders\Enums\OrderCondition;
use App\Modules\Orders\Enums\OrderSource;
use App\Modules\Orders\Http\Requests\StorePublicOrderRequest;
use App\Modules\Orders\Support\OrderSummaryFormatter;
use App\Modules\Orders\Support\WhatsAppOrderLink;
use Illuminate\Http\JsonResponse;

final class PublicOrderController
{
    public function __invoke(StorePublicOrderRequest $request, ResolvePublicCheckoutSelections $resolveSelections, CreateOrder $createOrder, OrderSummaryFormatter $formatter, WhatsAppOrderLink $whatsApp): JsonResponse
    {
        $selections = $resolveSelections->execute(
            $request->validated('items'), $request->validated('addons', []),
            OrderCondition::from($request->validated('condition')),
        );
        $order = $createOrder->execute(OrderCheckoutData::fromRequest($request, true, $selections['items'], $selections['addons']), OrderSource::PublicCheckout);

        return response()->json([
            'data' => [
                'order_id' => $order->public_id,
                'status' => $order->status->value,
                'payment_status' => $order->payment_status->value,
                'total' => (int) $order->total,
                'summary' => $formatter->format($order),
                'whatsapp_url' => $whatsApp->for($order),
            ],
        ], $order->wasRecentlyCreated ? 201 : 200);
    }
}
