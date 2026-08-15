<?php

namespace App\Modules\Orders\Http\Controllers;

use App\Modules\Orders\Actions\CalculateOrderPrice;
use App\Modules\Orders\Enums\DeliveryType;
use App\Modules\Orders\Enums\DiscountType;
use App\Modules\Orders\Enums\OrderCondition;
use App\Modules\Orders\Http\Requests\PreviewOrderPriceRequest;
use App\Modules\Orders\Pricing\OrderPricingEngine;
use Illuminate\Http\JsonResponse;

final class OrderPricingController
{
    public function __invoke(PreviewOrderPriceRequest $request, OrderPricingEngine $pricing, CalculateOrderPrice $legacyPricing): JsonResponse
    {
        if ($request->validated('condition') === null) {
            return response()->json($legacyPricing->execute($request->validated('items')));
        }
        $quote = $pricing->quote(
            $request->validated('items'),
            OrderCondition::from($request->validated('condition')),
            $request->validated('addons', []),
            DeliveryType::from($request->validated('delivery_type', 'pickup')),
            $request->validated('discount_type') === null ? null : DiscountType::from($request->validated('discount_type')),
            $request->validated('discount_value'),
        );

        return response()->json(['data' => $quote->toArray()]);
    }
}
