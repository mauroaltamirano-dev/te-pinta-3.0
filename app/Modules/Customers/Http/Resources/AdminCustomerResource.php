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
            'orders_count' => (int) ($this->orders_count ?? 0),
            'historical_spend' => (int) ($this->effective_orders_sum_total ?? 0),
            'latest_order_date' => $this->whenLoaded(
                'latestOrder',
                fn (): ?string => $this->latestOrder === null
                    ? null
                    : CarbonImmutable::parse($this->latestOrder->delivery_date)->toDateString(),
            ),
            'orders' => $this->whenLoaded('orders', fn (): array => $this->orders
                ->map($this->serializeOrder(...))
                ->all()),
        ];
    }

    /** @return array{id: string, date: string, delivery_type: string, status: string, payment_status: string, payment_method: ?string, total: int} */
    private function serializeOrder(Order $order): array
    {
        return [
            'id' => $order->public_id,
            'date' => CarbonImmutable::parse($order->delivery_date)->toDateString(),
            'delivery_type' => $order->delivery_type->value,
            'status' => $order->status->value,
            'payment_status' => $order->payment_status->value,
            'payment_method' => $order->payment_method?->value,
            'total' => (int) $order->total,
        ];
    }
}
