<?php

namespace App\Modules\Orders\Support;

use App\Modules\Orders\Enums\DeliveryType;
use App\Modules\Orders\Models\Order;
use App\Modules\Orders\Models\OrderItem;

final class OrderSummaryFormatter
{
    public function formatAdmin(Order $order): string
    {
        $order->loadMissing(['items.compositions', 'toppings']);

        $lines = [
            "🧾 *PEDIDO {$order->public_id}*",
            '',
            ...($order->customer_name_snapshot === null ? [] : ["👤 *Cliente:* {$order->customer_name_snapshot}"]),
            '🛵 *Modalidad:* '.$this->fulfillmentLabel($order->delivery_type),
            ...($order->condition->value === 'raw' ? ['🥟 *Crudas*'] : []),
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
        array_push($lines, ...$this->summaryLines($order));
        $lines[] = '';
        $lines[] = '💰 *TOTAL: '.$this->money((int) $order->total).'*';

        if (filled($order->notes)) {
            $lines[] = '';
            $lines[] = "📝 *Nota:* {$order->notes}";
        }

        return implode("\n", $lines);
    }

    public function format(Order $order): string
    {
        $order->loadMissing(['items.compositions', 'toppings']);
        $lines = ["Pedido {$order->public_id}", '', ...($order->customer_name_snapshot === null ? [] : ["Cliente: {$order->customer_name_snapshot}"]),
            'Modalidad: '.$this->fulfillmentLabel($order->delivery_type),
            ...($order->condition->value === 'raw' ? ['Crudas'] : []), ''];
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
        array_push($lines, ...$this->summaryLines($order));
        $lines[] = 'Total: '.$this->money((int) $order->total);
        if ($order->notes !== null) {
            $lines[] = '';
            $lines[] = "Nota: {$order->notes}";
        }

        return implode("\n", $lines);
    }

    /**
     * Products at their menu (cooked) price, then one discount that folds the combined dozen, the raw
     * discount, the admin discount and the final rounding, so the lines always add up to the total.
     *
     * @return list<string>
     */
    private function summaryLines(Order $order): array
    {
        $products = $order->items->isEmpty()
            ? (int) ($order->items_cooked_subtotal ?? $order->items_subtotal)
            : $order->items->sum(fn (OrderItem $item): int => $this->menuPrice($item));
        $toppings = (int) $order->toppings_subtotal;
        $shipping = (int) $order->shipping_fee_snapshot;
        $discount = $products + $toppings + $shipping - (int) $order->total;

        return [
            'Productos: '.$this->money($products),
            ...($discount > 0 ? ['Descuento: -'.$this->money($discount)] : []),
            ...($toppings > 0 ? ['Toppings: '.$this->money($toppings)] : []),
            ...($shipping > 0 ? ['Envío: '.$this->money($shipping)] : []),
        ];
    }

    /** A combined dozen's regular price scales its cooked total by the same ratio as the applied one. */
    private function menuPrice(OrderItem $item): int
    {
        $applied = (int) $item->line_total;
        $cooked = $item->cooked_line_total === null ? $applied : (int) $item->cooked_line_total;
        $regular = $item->regular_price_total === null ? $applied : (int) $item->regular_price_total;

        return $applied > 0 && $regular > $applied ? intdiv($cooked * $regular + intdiv($applied, 2), $applied) : $cooked;
    }

    private function fulfillmentLabel(DeliveryType $type): string
    {
        return match ($type) {
            DeliveryType::Delivery => 'Envío',
            DeliveryType::Pickup => 'Retiro',
            DeliveryType::DineIn => 'Salón',
        };
    }

    private function money(int $amount): string
    {
        return '$'.number_format($amount, 0, ',', '.');
    }

    private function quantityLabel(int $quantity, string $singular, string $plural): string
    {
        return $quantity.' '.($quantity === 1 ? $singular : $plural);
    }
}
