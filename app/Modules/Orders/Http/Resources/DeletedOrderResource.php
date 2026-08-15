<?php

namespace App\Modules\Orders\Http\Resources;

use App\Modules\Orders\Models\Order;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Order */
final class DeletedOrderResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->public_id,
            'customer_name' => $this->customer_name_snapshot,
            'status' => $this->status->value,
            'payment_status' => $this->payment_status->value,
            'total' => (int) $this->total,
            'delivery_date' => CarbonImmutable::parse($this->delivery_date)->toDateString(),
            'deleted_at' => $this->deleted_at?->toJSON(),
        ];
    }
}
