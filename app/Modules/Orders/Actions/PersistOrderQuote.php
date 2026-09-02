<?php

namespace App\Modules\Orders\Actions;

use App\Modules\Orders\Enums\DiscountType;
use App\Modules\Orders\Models\Order;
use App\Modules\Orders\Pricing\OrderPriceQuote;

final class PersistOrderQuote
{
    public function execute(Order $order, OrderPriceQuote $quote, bool $replace = false): void
    {
        if ($replace) {
            $order->items()->delete();
            $order->toppings()->delete();
        }

        foreach ($quote->lines as $line) {
            $item = $order->items()->create([
                'product_id' => $line->productId,
                'product_name' => $line->productName ?? 'Combined dozen',
                'pricing_mode' => $line->pricingMode,
                'quantity' => $line->quantity,
                'units_count' => $line->unitsCount,
                'unit_price' => $line->unitPrice,
                'line_total' => $line->lineTotal,
                'cooked_unit_price' => $line->cookedUnitPrice,
                'cooked_line_total' => $line->cookedLineTotal,
                'raw_discount' => $line->rawDiscount,
                'regular_price_total' => $line->regularPriceTotal,
                'savings' => $line->savings,
            ]);
            foreach ($line->composition as $part) {
                $item->compositions()->create($part);
            }
        }

        foreach ($quote->addons as $addon) {
            $order->toppings()->create([
                'order_addon_id' => $addon->addonId,
                'name' => $addon->name,
                'quantity' => $addon->quantity,
                'unit_price' => $addon->unitPrice,
                'subtotal' => $addon->lineTotal,
            ]);
        }
    }

    /** @return array<string, DiscountType|int|null> */
    public function totals(OrderPriceQuote $quote): array
    {
        return [
            'items_base_subtotal' => null,
            'cooking_surcharge' => null,
            'items_cooked_subtotal' => $quote->productsCookedSubtotal,
            'raw_discount' => $quote->rawDiscount,
            'items_subtotal' => $quote->productsSubtotal,
            'toppings_subtotal' => $quote->addonsSubtotal,
            'shipping_fee_snapshot' => $quote->shippingFee,
            'combined_dozen_savings' => $quote->savings,
            'discount_type' => $quote->discountType,
            'discount_value' => $quote->discountValue,
            'discount_amount' => $quote->discountAmount,
            'total' => $quote->total,
        ];
    }
}
