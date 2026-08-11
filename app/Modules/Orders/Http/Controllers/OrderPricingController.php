<?php

namespace App\Modules\Orders\Http\Controllers;

use App\Modules\Orders\Actions\CalculateOrderPrice;
use App\Modules\Orders\Http\Requests\PreviewOrderPriceRequest;
use Illuminate\Http\JsonResponse;

final class OrderPricingController
{
    public function __invoke(PreviewOrderPriceRequest $request, CalculateOrderPrice $calculator): JsonResponse
    {
        return response()->json($calculator->execute($request->validated('items')));
    }
}
