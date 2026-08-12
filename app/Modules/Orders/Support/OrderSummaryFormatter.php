<?php

namespace App\Modules\Orders\Support;

use App\Modules\Orders\Models\Order;
use Carbon\CarbonImmutable;

final class OrderSummaryFormatter
{
    public function format(Order $order): string
    {
        $order->loadMissing(['items.compositions', 'toppings']);
        $lines = ["Order {$order->public_id}", '', "Customer: {$order->customer_name_snapshot}",
            'Delivery: '.CarbonImmutable::parse($order->delivery_date)->toDateString().' - '.$order->getRawOriginal('delivery_period'),
            'Method: '.($order->delivery_type->value === 'delivery' ? 'Delivery' : 'Pickup'),
            'Condition: '.($order->condition->value === 'cooked' ? 'Cooked' : 'Raw'), ''];
        foreach ($order->items as $item) {
            $label = match ($item->getRawOriginal('pricing_mode')) {
                'unit' => "{$item->quantity} unit(s) {$item->product_name}",
                'half_dozen' => "{$item->quantity} half-dozen {$item->product_name}",
                'dozen' => "{$item->quantity} dozen {$item->product_name}",
                default => "{$item->quantity} combined dozen",
            };
            $lines[] = $label;
            foreach ($item->compositions as $part) {
                $lines[] = "- {$part->product_name} x{$part->quantity}";
            }
        }
        if ($order->toppings->isNotEmpty()) {
            $lines[] = '';
            foreach ($order->toppings as $topping) {
                $lines[] = "{$topping->name} x{$topping->quantity}";
            }
        }
        $lines[] = '';
        $lines[] = 'Empanadas: '.$this->money((int) $order->items_subtotal);
        $lines[] = 'Toppings: '.$this->money((int) $order->toppings_subtotal);
        $lines[] = 'Shipping: '.$this->money((int) $order->shipping_fee_snapshot);
        if ((int) $order->combined_dozen_savings > 0) {
            $lines[] = 'Combined-dozen savings: '.$this->money((int) $order->combined_dozen_savings);
        }
        $lines[] = 'Total: '.$this->money((int) $order->total);
        if ($order->notes !== null) {
            $lines[] = '';
            $lines[] = "Note: {$order->notes}";
        }

        return implode("\n", $lines);
    }

    private function money(int $amount): string
    {
        return '$'.number_format($amount, 0, ',', '.');
    }
}
