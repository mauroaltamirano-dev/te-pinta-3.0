<?php

namespace App\Modules\Orders\Support;

use App\Modules\Identity\Models\User;
use App\Modules\Orders\Enums\DeliveryType;
use App\Modules\Orders\Enums\OrderCondition;
use App\Modules\Orders\Enums\PricingMode;
use App\Modules\Orders\Enums\TableRoundKind;
use App\Modules\Orders\Events\PrintableDocumentIssued;
use App\Modules\Orders\Models\Order;
use App\Modules\Orders\Models\OrderItem;
use App\Modules\Orders\Models\TableRound;
use App\Modules\Orders\Models\TableSession;
use App\Modules\Restaurant\Queries\ServiceTables;

/** Builds the frozen print payloads: kitchen tickets have no prices, bills do. */
final readonly class OrderDocuments
{
    public const BILL_LEGEND = 'Documento no válido como factura';

    private const PERIODS = ['midday' => 'Mediodía', 'afternoon' => 'Tarde', 'night' => 'Noche'];

    public function __construct(private OrderBusinessDate $businessDate, private ServiceTables $tables) {}

    public function kitchenTicket(TableSession $session, TableRound $round, User $actor): PrintableDocumentIssued
    {
        $now = $this->businessDate->today();
        $waiter = $session->waiter;

        return new PrintableDocumentIssued('kitchen_ticket', "table-round:{$round->id}", $session->order_id, [
            'title' => $this->title($session),
            'order' => $session->order->public_id,
            'round' => $round->sequence,
            'void' => $round->kind === TableRoundKind::Void,
            'date' => $now->toDateString(),
            'time' => $now->format('H:i'),
            'waiter' => $waiter->name,
            'added_by' => $actor->id === $waiter->id ? null : $round->confirmed_by_role->label().' '.$actor->name,
            'note' => $round->note,
            'lines' => $round->items->map(fn ($item): array => ['name' => $item->name, 'quantity' => $item->quantity])->all(),
        ], $actor->id);
    }

    /**
     * The kitchen ticket of a whole order outside table service (admin, counter, confirmed public order):
     * units per product, never prices. One per order (dedupe key), however often its status changes.
     */
    public function orderKitchenTicket(Order $order, int $actorId): PrintableDocumentIssued
    {
        $now = $this->businessDate->today();
        $units = [];
        foreach ($order->items()->orderBy('id')->get() as $item) {
            $key = $item->product_id === null ? 'n'.$item->product_name : 'p'.$item->product_id;
            $units[$key] ??= ['name' => $item->product_name, 'quantity' => 0];
            $units[$key]['quantity'] += $item->units_count;
        }
        $addons = $order->toppings()->orderBy('id')->get()->map(fn ($topping): array => ['name' => $topping->name, 'quantity' => $topping->quantity]);
        $period = $order->delivery_period === null ? null : self::PERIODS[$order->delivery_period->value];
        $schedule = $period === null ? null : trim($period.' '.substr((string) $order->delivery_time, 0, 5));

        return new PrintableDocumentIssued('kitchen_ticket', "order:{$order->id}:kitchen", $order->id, [
            'title' => match ($order->delivery_type) {
                DeliveryType::Delivery => 'Delivery',
                DeliveryType::Pickup => 'Retiro',
                DeliveryType::DineIn => 'Mostrador',
            },
            'order' => $order->public_id,
            'round' => null,
            'void' => false,
            'date' => $now->toDateString(),
            'time' => $now->format('H:i'),
            'waiter' => null,
            'added_by' => null,
            'customer' => $order->customer_name_snapshot,
            'condition' => $order->condition === OrderCondition::Raw ? 'Crudas (para cocinar en casa)' : null,
            'schedule' => $schedule,
            'delivery_date' => $order->delivery_date?->toDateString(),
            'note' => $order->notes,
            'lines' => [...array_values($units), ...$addons->all()],
        ], $actorId);
    }

    public function bill(TableSession $session, Order $order, User $actor): PrintableDocumentIssued
    {
        $now = $this->businessDate->today();
        $lines = $order->items()->orderBy('id')->get()->map(fn (OrderItem $item): array => [
            'name' => $item->product_name,
            'detail' => $item->getRawOriginal('pricing_mode') === PricingMode::Dozen->value ? ($item->quantity === 1 ? '1 docena' : "{$item->quantity} docenas") : null,
            'quantity' => $item->quantity,
            'unit_price' => $item->unit_price,
            'total' => $item->line_total,
        ]);
        $addons = $order->toppings()->orderBy('id')->get()->map(fn ($topping): array => [
            'name' => $topping->name, 'detail' => null, 'quantity' => $topping->quantity, 'unit_price' => $topping->unit_price, 'total' => $topping->subtotal,
        ]);
        $subtotal = (int) $order->items_subtotal + (int) $order->toppings_subtotal;
        $discount = (int) $order->discount_amount;

        return new PrintableDocumentIssued('bill', "order:{$order->id}:bill:v{$order->edit_version}", $order->id, [
            'title' => $this->title($session),
            'order' => $order->public_id,
            'date' => $now->toDateString(),
            'time' => $now->format('H:i'),
            'waiter' => $session->waiter->name,
            'lines' => [...$lines->all(), ...$addons->all()],
            'subtotal' => $subtotal,
            'discount' => $discount,
            'rounding' => (int) $order->total - ($subtotal - $discount + (int) $order->shipping_fee_snapshot),
            'total' => (int) $order->total,
            'legend' => self::BILL_LEGEND,
        ], $actor->id);
    }

    private function title(TableSession $session): string
    {
        return 'Mesa '.$this->tables->code($session->dining_table_id);
    }
}
