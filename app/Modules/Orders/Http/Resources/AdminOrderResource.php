<?php

namespace App\Modules\Orders\Http\Resources;

use App\Modules\Orders\Models\Order;
use App\Modules\Orders\Models\OrderItem;
use App\Modules\Orders\Models\OrderItemComposition;
use App\Modules\Orders\Models\OrderTopping;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Order */
final class AdminOrderResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->public_id,
            'customer' => $this->customer_id === null ? null : ['id' => $this->customer_id, 'name' => $this->customer_name_snapshot],
            'customer_name' => $this->customer_name_snapshot,
            'source' => $this->source->value,
            'status' => $this->status->value,
            'payment_status' => $this->payment_status->value,
            'payment_method' => $this->payment_method?->value,
            'condition' => $this->condition->value,
            'delivery_type' => $this->delivery_type->value,
            'delivery_period' => $this->getRawOriginal('delivery_period'),
            'delivery_date' => $this->delivery_date === null ? null : CarbonImmutable::parse($this->delivery_date)->toDateString(),
            'delivery_time' => $this->delivery_time,
            'delivery_address' => $this->delivery_address_snapshot,
            'notes' => $this->notes,
            'items_base_subtotal' => $this->items_base_subtotal === null ? null : (int) $this->items_base_subtotal,
            'cooking_surcharge' => $this->cooking_surcharge === null ? null : (int) $this->cooking_surcharge,
            'items_cooked_subtotal' => $this->items_cooked_subtotal === null ? null : (int) $this->items_cooked_subtotal,
            'raw_discount' => $this->raw_discount === null ? null : (int) $this->raw_discount,
            'items_subtotal' => (int) $this->items_subtotal,
            'toppings_subtotal' => (int) $this->toppings_subtotal,
            'shipping_fee' => (int) $this->shipping_fee_snapshot,
            'combined_dozen_savings' => (int) $this->combined_dozen_savings,
            'discount_type' => $this->discount_type?->value,
            'discount_value' => $this->discount_value,
            'discount_amount' => (int) $this->discount_amount,
            'total' => (int) $this->total,
            'version' => (int) $this->edit_version,
            'closed' => $this->status->isClosed(),
            'items' => $this->whenLoaded('items', fn () => $this->items->map(fn (OrderItem $item): array => [
                'product_id' => $item->product_id, 'product_name' => $item->product_name,
                'pricing_mode' => $item->getRawOriginal('pricing_mode'), 'quantity' => $item->quantity,
                'units_count' => $item->units_count, 'unit_price' => $item->unit_price,
                'subtotal' => $item->line_total,
                'base_unit_price' => $item->base_unit_price,
                'base_subtotal' => $item->base_line_total,
                'cooking_surcharge' => $item->cooking_surcharge,
                'cooked_unit_price' => $item->cooked_unit_price,
                'cooked_subtotal' => $item->cooked_line_total,
                'raw_discount' => $item->raw_discount,
                'regular_price_total' => $item->regular_price_total,
                'savings' => $item->savings,
                'composition' => $item->relationLoaded('compositions') ? $item->compositions->map(fn (OrderItemComposition $part): array => [
                    'product_name' => $part->product_name, 'quantity' => $part->quantity,
                ])->all() : [],
            ])->all()),
            'toppings' => $this->whenLoaded('toppings', fn () => $this->toppings->map(fn (OrderTopping $topping): array => [
                'name' => $topping->name, 'quantity' => $topping->quantity,
                'unit_price' => $topping->unit_price, 'subtotal' => $topping->subtotal,
            ])->all()),
        ];
    }
}
