<?php

namespace App\Modules\Orders\Support;

use App\Modules\Orders\Models\Order;
use Carbon\CarbonImmutable;

final class OrderSummaryFormatter
{
    public function format(Order $order): string
    {
        $order->loadMissing(['items.compositions', 'toppings']);
        $lines = ["Pedido {$order->public_id}", '', "Cliente: {$order->customer_name_snapshot}",
            'Entrega: '.CarbonImmutable::parse($order->delivery_date)->format('d/m/Y').' - '.$this->deliveryPeriod($order->getRawOriginal('delivery_period')),
            'Modalidad: '.($order->delivery_type->value === 'delivery' ? 'Envío' : 'Retiro'),
            'Condición: '.($order->condition->value === 'cooked' ? 'Cocinado' : 'Crudo'), ''];
        foreach ($order->items as $item) {
            $label = match ($item->getRawOriginal('pricing_mode')) {
                'unit' => $this->quantityLabel($item->quantity, 'unidad', 'unidades')." de {$item->product_name}",
                'half_dozen' => $this->quantityLabel($item->quantity, 'media docena', 'medias docenas')." de {$item->product_name}",
                'dozen' => $this->quantityLabel($item->quantity, 'docena', 'docenas')." de {$item->product_name}",
                default => $this->quantityLabel($item->quantity, 'docena combinada', 'docenas combinadas'),
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
        $lines[] = ($order->items_base_subtotal === null ? 'Empanadas' : 'Empanadas base').': '.$this->money(
            (int) ($order->items_base_subtotal ?? $order->items_subtotal),
        );
        if ($order->cooking_surcharge !== null && (int) $order->cooking_surcharge !== 0) {
            $lines[] = 'Cocción: '.$this->signedMoney((int) $order->cooking_surcharge);
        }
        $lines[] = 'Toppings: '.$this->money((int) $order->toppings_subtotal);
        $lines[] = 'Envío: '.$this->money((int) $order->shipping_fee_snapshot);
        if ((int) $order->combined_dozen_savings > 0) {
            $lines[] = 'Ahorro por docena combinada: '.$this->money((int) $order->combined_dozen_savings);
        }
        $lines[] = 'Total: '.$this->money((int) $order->total);
        if ($order->notes !== null) {
            $lines[] = '';
            $lines[] = "Nota: {$order->notes}";
        }

        return implode("\n", $lines);
    }

    private function money(int $amount): string
    {
        return '$'.number_format($amount, 0, ',', '.');
    }

    private function signedMoney(int $amount): string
    {
        $sign = $amount > 0 ? '+' : ($amount < 0 ? '-' : '');

        return $sign.$this->money(abs($amount));
    }

    private function quantityLabel(int $quantity, string $singular, string $plural): string
    {
        return $quantity.' '.($quantity === 1 ? $singular : $plural);
    }

    private function deliveryPeriod(?string $period): string
    {
        return match ($period) {
            'midday' => 'Mediodía',
            'afternoon' => 'Tarde',
            'night' => 'Noche',
            default => $period ?? 'Sin franja',
        };
    }
}
