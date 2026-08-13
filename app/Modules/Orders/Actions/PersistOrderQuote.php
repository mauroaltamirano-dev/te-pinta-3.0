<?php

namespace App\Modules\Orders\Actions;

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
                'base_unit_price' => $line->baseUnitPrice,
                'base_line_total' => $line->baseLineTotal,
                'cooking_surcharge' => $line->cookingSurcharge,
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

    /** @return array<string, int> */
    public function totals(OrderPriceQuote $quote): array
    {
        return [
            'items_base_subtotal' => $quote->productsBaseSubtotal,
            'cooking_surcharge' => $quote->cookingSurcharge,
            'items_subtotal' => $quote->productsSubtotal,
            'toppings_subtotal' => $quote->addonsSubtotal,
            'shipping_fee_snapshot' => $quote->shippingFee,
            'combined_dozen_savings' => $quote->savings,
            'total' => $quote->total,
        ];
    }
}
