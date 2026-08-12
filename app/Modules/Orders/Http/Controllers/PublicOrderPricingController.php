<?php

namespace App\Modules\Orders\Http\Controllers;

use App\Modules\Orders\Actions\ResolvePublicCheckoutSelections;
use App\Modules\Orders\Enums\DeliveryType;
use App\Modules\Orders\Enums\OrderCondition;
use App\Modules\Orders\Http\Requests\PreviewPublicOrderPriceRequest;
use App\Modules\Orders\Pricing\OrderPricingEngine;
use Illuminate\Http\JsonResponse;

final class PublicOrderPricingController
{
    public function __invoke(PreviewPublicOrderPriceRequest $request, ResolvePublicCheckoutSelections $resolver, OrderPricingEngine $pricing): JsonResponse
    {
        $condition = OrderCondition::from($request->validated('condition'));
        $selections = $resolver->execute($request->validated('items'), $request->validated('addons', []), $condition);

        return response()->json(['data' => $pricing->quote(
            $selections['items'], $condition, $selections['addons'], DeliveryType::from($request->validated('delivery_type')),
        )->toArray()]);
    }
}
