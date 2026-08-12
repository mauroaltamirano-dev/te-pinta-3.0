<?php

namespace App\Modules\Customers\Http\Resources;

use App\Modules\Customers\Models\Customer;
use App\Modules\Orders\Models\Order;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Customer */
final class AdminCustomerResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'phone' => $this->phone,
            'address' => $this->address,
            'notes' => $this->notes,
            'historical_spend' => (int) ($this->effective_orders_sum_total ?? 0),
            'orders' => $this->whenLoaded('orders', fn (): array => $this->orders
                ->map($this->serializeOrder(...))
                ->all()),
        ];
    }

    /** @return array{id: string, date: string, status: string, total: int} */
    private function serializeOrder(Order $order): array
    {
        return [
            'id' => $order->public_id,
            'date' => CarbonImmutable::parse($order->delivery_date)->toDateString(),
            'status' => $order->status->value,
            'total' => (int) $order->total,
        ];
    }
}
