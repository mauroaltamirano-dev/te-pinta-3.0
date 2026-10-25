<?php

namespace App\Modules\Orders\Http\Controllers;

use App\Modules\Orders\Models\OrderAddon;
use App\Modules\Orders\Support\OrderBusinessDate;
use App\Modules\Orders\Support\PublicOrderingHours;
use Illuminate\Http\JsonResponse;

final class PublicCheckoutOptionsController
{
    public function __invoke(OrderBusinessDate $businessDate, PublicOrderingHours $hours): JsonResponse
    {
        return response()->json(['data' => [
            'addons' => OrderAddon::query()
                ->where('is_available', true)->orderBy('sort_order')->get(['slug', 'name', 'price']),
            'today' => $businessDate->todayString(),
            'ordering' => $hours->status(),
        ]]);
    }
}
