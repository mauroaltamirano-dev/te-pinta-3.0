<?php

namespace App\Modules\Orders\Http\Controllers;

use App\Modules\Orders\Models\OrderAddon;
use App\Modules\Orders\Support\OrderBusinessDate;
use Illuminate\Http\JsonResponse;

final class PublicCheckoutOptionsController
{
    public function __invoke(OrderBusinessDate $businessDate): JsonResponse
    {
        return response()->json(['data' => [
            'addons' => OrderAddon::query()
                ->where('is_available', true)->orderBy('sort_order')->get(['slug', 'name', 'price']),
            'today' => $businessDate->todayString(),
        ]]);
    }
}
