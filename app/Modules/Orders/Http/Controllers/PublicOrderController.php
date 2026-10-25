<?php

namespace App\Modules\Orders\Http\Controllers;

use App\Modules\Orders\Actions\CreateOrder;
use App\Modules\Orders\Actions\ResolvePublicCheckoutSelections;
use App\Modules\Orders\Data\OrderCheckoutData;
use App\Modules\Orders\Enums\OrderCondition;
use App\Modules\Orders\Enums\OrderSource;
use App\Modules\Orders\Http\Requests\StorePublicOrderRequest;
use App\Modules\Orders\Support\OrderSummaryFormatter;
use App\Modules\Orders\Support\PublicOrderingHours;
use App\Modules\Orders\Support\WhatsAppOrderLink;
use Illuminate\Http\JsonResponse;

final class PublicOrderController
{
    public function __invoke(StorePublicOrderRequest $request, ResolvePublicCheckoutSelections $resolveSelections, CreateOrder $createOrder, OrderSummaryFormatter $formatter, WhatsAppOrderLink $whatsApp, PublicOrderingHours $hours): JsonResponse
    {
        $window = $hours->assertOpen();
        $selections = $resolveSelections->execute(
            $request->validated('items'), $request->validated('addons', []),
            OrderCondition::from($request->validated('condition')),
        );
        $order = $createOrder->execute(OrderCheckoutData::fromRequest($request, true, $selections['items'], $selections['addons'], $window), OrderSource::PublicCheckout);

        return response()->json([
            'data' => [
                'order_id' => $order->public_id,
                'status' => $order->status->value,
                'payment_status' => $order->payment_status->value,
                'items_base_subtotal' => $order->items_base_subtotal === null ? null : (int) $order->items_base_subtotal,
                'cooking_surcharge' => $order->cooking_surcharge === null ? null : (int) $order->cooking_surcharge,
                'items_cooked_subtotal' => $order->items_cooked_subtotal === null ? null : (int) $order->items_cooked_subtotal,
                'raw_discount' => $order->raw_discount === null ? null : (int) $order->raw_discount,
                'total' => (int) $order->total,
                'summary' => $formatter->format($order),
                'whatsapp_url' => $whatsApp->for($order),
            ],
        ], $order->wasRecentlyCreated ? 201 : 200);
    }
}
