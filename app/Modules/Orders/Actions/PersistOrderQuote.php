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
                // Version 2 (fixed dozen): whole-peso lines, so exact numerators are twelve times the pesos.
                'pricing_snapshot_version' => 2,
                'applied_exact_numerator_12' => $line->lineTotal * 12,
                'cooked_exact_numerator_12' => $line->cookedLineTotal * 12,
                'raw_discount_exact_numerator_12' => $line->rawDiscount * 12,
                'surcharge_exact_numerator_12' => 0,
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
            // New quotes are always version 2; historical orders keep their own version.
            'pricing_snapshot_version' => 2,
            'product_exact_numerator_12' => $quote->productsSubtotal * 12,
            'cooked_product_exact_numerator_12' => $quote->productsCookedSubtotal * 12,
            'raw_discount_exact_numerator_12' => $quote->rawDiscount * 12,
            'discount_exact_numerator_1200' => $quote->discountExactNumerator1200,
            'net_pre_round_exact_numerator_1200' => $quote->netPreRoundExactNumerator1200,
            'rounding_adjustment_numerator_1200' => $quote->roundingAdjustmentNumerator1200,
        ];
    }
}
