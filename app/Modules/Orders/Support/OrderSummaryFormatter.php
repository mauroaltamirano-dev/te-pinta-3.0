<?php

namespace App\Modules\Orders\Support;

use App\Modules\Orders\Models\Order;
use Carbon\CarbonImmutable;

final class OrderSummaryFormatter
{
    public function formatAdmin(Order $order): string
    {
        $order->loadMissing(['items.compositions', 'toppings']);
        $delivery = CarbonImmutable::parse($order->delivery_date)->format('d/m/Y').' · '.$this->deliveryPeriod($order->getRawOriginal('delivery_period'));
        if ($order->delivery_time !== null) {
            $delivery .= ' · '.CarbonImmutable::parse($order->delivery_time)->format('H:i');
        }

        $lines = [
            "🧾 *PEDIDO {$order->public_id}*",
            '',
            "👤 *Cliente:* {$order->customer_name_snapshot}",
            "📅 *Entrega:* {$delivery}",
            '🛵 *Modalidad:* '.($order->delivery_type->value === 'delivery' ? 'Envío' : 'Retiro'),
            '🔥 *Condición:* '.($order->condition->value === 'cooked' ? 'Cocinadas' : 'Crudas'),
            '',
            '*DETALLE DEL PEDIDO*',
            '',
        ];

        foreach ($order->items as $index => $item) {
            $label = match ($item->getRawOriginal('pricing_mode')) {
                'unit' => "{$item->quantity} × {$item->product_name}",
                'half_dozen' => "{$item->quantity} × ".($item->quantity === 1 ? 'Media docena' : 'Medias docenas')." de {$item->product_name}",
                'dozen' => "{$item->quantity} × ".($item->quantity === 1 ? 'Docena' : 'Docenas')." de {$item->product_name}",
                default => "{$item->quantity} × ".($item->quantity === 1 ? 'Docena combinada' : 'Docenas combinadas'),
            };
            $lines[] = "• {$label}";
            foreach ($item->compositions as $part) {
                $lines[] = "└ {$part->quantity} {$part->product_name}";
            }
            if ($index < $order->items->count() - 1) {
                $lines[] = '';
            }
        }

        if ($order->toppings->isNotEmpty()) {
            $lines[] = '';
            $lines[] = '*ADICIONALES*';
            $lines[] = '';
            foreach ($order->toppings as $topping) {
                $lines[] = "• {$topping->quantity} × {$topping->name}";
            }
        }

        $lines[] = '';
        $lines[] = '*RESUMEN*';
        $lines[] = '';
        $lines[] = ($order->items_cooked_subtotal === null ? 'Empanadas' : 'Empanadas cocinadas').': '.$this->money(
            (int) ($order->items_cooked_subtotal ?? $order->items_subtotal),
        );
        if ($order->raw_discount !== null && (int) $order->raw_discount !== 0) {
            $lines[] = 'Descuento por preparación cruda: -'.$this->money((int) $order->raw_discount);
        }
        $lines[] = 'Envío: '.$this->signedMoney((int) $order->shipping_fee_snapshot);
        $lines[] = 'Toppings: '.$this->money((int) $order->toppings_subtotal);
        if ((int) $order->discount_amount > 0) {
            $lines[] = 'Descuento: -'.$this->money((int) $order->discount_amount);
        }
        $lines[] = '';
        $lines[] = '💰 *TOTAL: '.$this->money((int) $order->total).'*';

        if ((int) $order->combined_dozen_savings > 0) {
            $lines[] = '';
            $lines[] = '🏷️ *Ahorro incluido por docena combinada: '.$this->money((int) $order->combined_dozen_savings).'*';
            $lines[] = '';
            $lines[] = '*El ahorro de la docena combinada ya está aplicado en el precio de las empanadas.*';
        }
        if (filled($order->notes)) {
            $lines[] = '';
            $lines[] = "📝 *Nota:* {$order->notes}";
        }

        return implode("\n", $lines);
    }

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
        $lines[] = ($order->items_cooked_subtotal === null ? 'Empanadas' : 'Empanadas cocinadas').': '.$this->money(
            (int) ($order->items_cooked_subtotal ?? $order->items_subtotal),
        );
        if ($order->raw_discount !== null && (int) $order->raw_discount !== 0) {
            $lines[] = 'Descuento por preparación cruda: -'.$this->money((int) $order->raw_discount);
        }
        $lines[] = 'Toppings: '.$this->money((int) $order->toppings_subtotal);
        $lines[] = 'Envío: '.$this->money((int) $order->shipping_fee_snapshot);
        if ((int) $order->combined_dozen_savings > 0) {
            $lines[] = 'Ahorro por docena combinada: '.$this->money((int) $order->combined_dozen_savings);
        }
        if ((int) $order->discount_amount > 0) {
            $lines[] = 'Descuento: -'.$this->money((int) $order->discount_amount);
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
