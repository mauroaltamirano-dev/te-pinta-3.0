<?php

namespace App\Modules\Orders\Actions;

use App\Modules\Orders\Models\Order;
use App\Modules\Orders\Models\OrderItem;
use App\Modules\Orders\Models\OrderItemComposition;
use App\Modules\Orders\Models\OrderTopping;
use Carbon\CarbonImmutable;

final readonly class BuildAdminOrderEditData
{
    public function __construct(private BuildAdminOrderFormOptions $options) {}

    /** @return array<string, mixed> */
    public function execute(Order $order, bool $closedEditGranted): array
    {
        $order->loadMissing(['items.compositions', 'toppings']);
        $items = $this->options->itemQuantities($order);
        $addons = $this->options->addonQuantities($order);

        return [
            'id' => $order->public_id,
            'status' => $order->status->value,
            'payment_status' => $order->payment_status->value,
            'payment_method' => $order->payment_method?->value,
            'total' => (int) $order->total,
            'version' => (int) $order->edit_version,
            'updated_at' => $order->updated_at->toJSON(),
            'closed' => $order->status->isClosed(),
            'closed_edit_required' => $order->status->isClosed() && ! $closedEditGranted,
            'draft' => [
                'items' => collect($items)->map(fn (int $quantity, int $id): array => ['product_id' => $id, 'quantity' => $quantity])->values()->all(),
                'addons' => collect($addons)->map(fn (int $quantity, int $id): array => ['addon_id' => $id, 'quantity' => $quantity])->values()->all(),
                'condition' => $order->condition->value,
                'customer_id' => $order->customer_id,
                'customer' => ['name' => $order->customer_name_snapshot ?? '', 'phone' => $order->customer_phone_snapshot ?? '', 'address' => ''],
                'delivery_type' => $order->delivery_type->value,
                'delivery_date' => $order->delivery_date === null ? '' : CarbonImmutable::parse($order->delivery_date)->toDateString(),
                'delivery_period' => $order->getRawOriginal('delivery_period'),
                'delivery_time' => $order->delivery_time === null ? '' : CarbonImmutable::parse($order->delivery_time)->format('H:i'),
                'delivery_address' => $order->delivery_address_snapshot ?? '',
                'notes' => $order->notes ?? '',
                'payment_status' => $order->payment_status->value,
                'payment_method' => $order->payment_method?->value,
                'discount_type' => $order->discount_type?->value,
                'discount_value' => $order->discount_value,
                'status' => $order->status->value,
                'expected_version' => (int) $order->edit_version,
            ],
            'original_quote' => [
                'condition' => $order->condition->value,
                'delivery_type' => $order->delivery_type->value,
                'lines' => $order->items->map(fn (OrderItem $item): array => [
                    'productName' => $item->product_name,
                    'pricingMode' => $item->getRawOriginal('pricing_mode'),
                    'quantity' => $item->quantity,
                    'unitPrice' => (int) $item->unit_price,
                    'lineTotal' => (int) $item->line_total,
                    'cookedUnitPrice' => $item->cooked_unit_price === null ? null : (int) $item->cooked_unit_price,
                    'cookedLineTotal' => $item->cooked_line_total === null ? null : (int) $item->cooked_line_total,
                    'rawDiscount' => $item->raw_discount === null ? null : (int) $item->raw_discount,
                    'composition' => $item->compositions->map(fn (OrderItemComposition $part): array => ['product_name' => $part->product_name, 'quantity' => $part->quantity])->all(),
                ])->all(),
                'addons' => $order->toppings->map(fn (OrderTopping $topping): array => ['name' => $topping->name, 'quantity' => $topping->quantity, 'unitPrice' => (int) $topping->unit_price, 'lineTotal' => (int) $topping->subtotal])->all(),
                'products_base_subtotal' => $order->items_base_subtotal === null ? null : (int) $order->items_base_subtotal,
                'cooking_surcharge' => $order->cooking_surcharge === null ? null : (int) $order->cooking_surcharge,
                'products_cooked_subtotal' => $order->items_cooked_subtotal === null ? null : (int) $order->items_cooked_subtotal,
                'raw_discount' => $order->raw_discount === null ? null : (int) $order->raw_discount,
                'products_subtotal' => (int) $order->items_subtotal,
                'addons_subtotal' => (int) $order->toppings_subtotal,
                'shipping_fee' => (int) $order->shipping_fee_snapshot,
                'savings' => (int) $order->combined_dozen_savings,
                'discountable_subtotal' => (int) $order->items_subtotal + (int) $order->toppings_subtotal,
                'discount_type' => $order->discount_type?->value,
                'discount_value' => $order->discount_value,
                'discount_amount' => (int) $order->discount_amount,
                'total' => (int) $order->total,
            ],
        ];
    }
}
