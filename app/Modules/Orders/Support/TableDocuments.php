<?php

namespace App\Modules\Orders\Support;

use App\Modules\Identity\Models\User;
use App\Modules\Orders\Enums\PricingMode;
use App\Modules\Orders\Enums\TableRoundKind;
use App\Modules\Orders\Events\PrintableDocumentIssued;
use App\Modules\Orders\Models\Order;
use App\Modules\Orders\Models\OrderItem;
use App\Modules\Orders\Models\TableRound;
use App\Modules\Orders\Models\TableSession;
use App\Modules\Restaurant\Queries\ServiceTables;

/** Builds the frozen print payloads of table service: kitchen tickets have no prices, bills do. */
final readonly class TableDocuments
{
    public const BILL_LEGEND = 'Documento no válido como factura';

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
