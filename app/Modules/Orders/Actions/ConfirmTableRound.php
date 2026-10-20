<?php

namespace App\Modules\Orders\Actions;

use App\Core\Tenancy\RestaurantContext;
use App\Modules\Identity\Models\User;
use App\Modules\Orders\Data\OrderCheckoutData;
use App\Modules\Orders\Enums\DeliveryType;
use App\Modules\Orders\Enums\OrderCondition;
use App\Modules\Orders\Enums\OrderSource;
use App\Modules\Orders\Enums\PaymentStatus;
use App\Modules\Orders\Enums\TableRoundKind;
use App\Modules\Orders\Enums\TableSessionStatus;
use App\Modules\Orders\Models\Order;
use App\Modules\Orders\Models\TableRound;
use App\Modules\Orders\Models\TableRoundItem;
use App\Modules\Orders\Models\TableSession;
use App\Modules\Orders\Pricing\OrderPricingEngine;
use App\Modules\Orders\Support\OrderDocuments;
use App\Modules\Restaurant\Queries\ServiceTables;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/**
 * Confirms one immutable round on a table. The first order round opens the session and its POS order;
 * every round re-prices that open order over the sum of all rounds, so 12 units of one flavor reach the
 * dozen price across rounds. Only the round's own lines go to the kitchen.
 */
final readonly class ConfirmTableRound
{
    public function __construct(
        private CreateOrder $createOrder,
        private OrderPricingEngine $pricing,
        private PersistOrderQuote $quotes,
        private ServiceTables $tables,
        private OrderDocuments $documents,
        private RestaurantContext $restaurant,
    ) {}

    /**
     * @param  array<int, array{product_id: int, quantity: int}>  $items
     * @param  array<int, array{addon_id: int, quantity: int}>  $addons
     * @param  int|null  $expectedSessionId  the open session the client was showing; null for a free table
     */
    public function execute(int $diningTableId, ?int $expectedSessionId, TableRoundKind $kind, array $items, array $addons, ?string $note, string $roundKey, User $actor): TableRound
    {
        if (! $actor->canUsePos()) {
            throw new AuthorizationException('The actor cannot take tables.');
        }
        $products = $this->lines($items, 'product_id', 'items');
        $extras = $this->lines($addons, 'addon_id', 'addons');
        if ($products === [] && $extras === []) {
            throw ValidationException::withMessages(['items' => 'Agregá al menos un producto a la ronda.']);
        }
        $note = $note === null || trim($note) === '' ? null : trim($note);
        if ($note !== null && mb_strlen($note) > 300) {
            throw ValidationException::withMessages(['note' => 'La nota admite como máximo 300 caracteres.']);
        }
        $hash = hash_hmac('sha256', 'table-round:'.$roundKey, (string) config('app.key'));
        $fingerprint = hash('sha256', json_encode([$diningTableId, $kind->value, $products, $extras, $note, $actor->id], JSON_THROW_ON_ERROR));

        if ($existing = $this->replay($hash, $fingerprint)) {
            return $existing;
        }

        try {
            $round = DB::transaction(fn (): TableRound => $this->confirm($diningTableId, $expectedSessionId, $kind, $products, $extras, $note, $hash, $fingerprint, $actor));
        } catch (QueryException $exception) {
            if ($exception->getCode() !== '23505' || ! ($existing = $this->replay($hash, $fingerprint))) {
                throw $exception;
            }

            return $existing;
        }

        Log::info('[Orders] table_round.confirmed', [
            'module' => 'Orders', 'table_session_id' => $round->table_session_id, 'round' => $round->sequence,
            'kind' => $round->kind->value, 'actor_id' => $actor->id,
        ]);

        return $round;
    }

    /**
     * @param  array<int, int>  $products
     * @param  array<int, int>  $extras
     */
    private function confirm(int $tableId, ?int $expectedSessionId, TableRoundKind $kind, array $products, array $extras, ?string $note, string $hash, string $fingerprint, User $actor): TableRound
    {
        $table = $this->tables->lock($tableId);
        if ($table === null) {
            throw ValidationException::withMessages(['table' => 'La mesa no existe.']);
        }
        // A duplicate that waited on the table lock replays the round its twin just committed.
        if ($existing = $this->replay($hash, $fingerprint)) {
            return $existing;
        }
        $session = TableSession::query()->where('dining_table_id', $tableId)->where('status', TableSessionStatus::Open)->lockForUpdate()->first();
        if ($session?->id !== $expectedSessionId) {
            throw ValidationException::withMessages(['table' => $session === null
                ? 'Esta mesa ya fue cobrada o cerrada. Actualizá la pantalla.'
                : 'La mesa ya está abierta. Actualizá la pantalla antes de cargar.']);
        }

        if ($session === null) {
            if ($kind === TableRoundKind::Void) {
                throw ValidationException::withMessages(['table' => 'La mesa no tiene una cuenta abierta para anular.']);
            }
            if (! $table['available']) {
                throw ValidationException::withMessages(['table' => 'La mesa no está activa.']);
            }
            $order = $this->createOrder->execute($this->orderData($products, $extras), OrderSource::Pos, $actor->id, kitchenTicket: false);
            $session = TableSession::query()->create(['dining_table_id' => $tableId, 'order_id' => $order->id, 'waiter_id' => $actor->id, 'opened_at' => now()]);
            $names = [];
        } else {
            if ($session->waiter_id !== $actor->id && ! $actor->canOperateCashier()) {
                throw new AuthorizationException('Only the table waiter or a cashier role can add to this table.');
            }
            $names = $this->requote($session, $kind, $products, $extras, $actor);
            $session->update(['bill_requested_at' => null, 'bill_requested_by' => null]);
        }

        $round = TableRound::query()->create([
            'table_session_id' => $session->id,
            'sequence' => (int) TableRound::query()->where('table_session_id', $session->id)->max('sequence') + 1,
            'kind' => $kind, 'note' => $note,
            'confirmed_by' => $actor->id, 'confirmed_by_role' => $actor->role(), 'confirmed_at' => now(),
            'idempotency_hash' => $hash, 'fingerprint' => $fingerprint,
        ]);
        $names += $this->snapshotNames($session->order_id);
        foreach ($products as $productId => $quantity) {
            $round->items()->save(new TableRoundItem(['product_id' => $productId, 'name' => $names["p{$productId}"], 'quantity' => $quantity]));
        }
        foreach ($extras as $addonId => $quantity) {
            $round->items()->save(new TableRoundItem(['order_addon_id' => $addonId, 'name' => $names["a{$addonId}"], 'quantity' => $quantity]));
        }

        $round->load(['items', 'session.order', 'session.waiter']);
        event($this->documents->kitchenTicket($round->session, $round, $actor));

        return $round;
    }

    /**
     * Re-prices the open order over every round plus this one and replaces only its snapshots.
     *
     * @param  array<int, int>  $products
     * @param  array<int, int>  $extras
     * @return array<string, string> names of previously ordered lines, for void rounds
     */
    private function requote(TableSession $session, TableRoundKind $kind, array $products, array $extras, User $actor): array
    {
        $sign = $kind === TableRoundKind::Void ? -1 : 1;
        $totals = ['p' => [], 'a' => []];
        $names = [];
        $previous = TableRoundItem::query()
            ->join('table_rounds as r', 'r.id', '=', 'table_round_items.table_round_id')
            ->where('r.table_session_id', $session->id)
            ->get(['table_round_items.*', 'r.kind']);
        foreach ($previous as $item) {
            [$group, $id] = $item->product_id !== null ? ['p', $item->product_id] : ['a', $item->order_addon_id];
            $totals[$group][$id] = ($totals[$group][$id] ?? 0) + ($item->getAttribute('kind') === TableRoundKind::Void->value ? -1 : 1) * $item->quantity;
            $names[$group.$id] = $item->name;
        }
        foreach ([['p', $products], ['a', $extras]] as [$group, $lines]) {
            foreach ($lines as $id => $quantity) {
                $totals[$group][$id] = ($totals[$group][$id] ?? 0) + $sign * $quantity;
                if ($totals[$group][$id] < 0) {
                    throw ValidationException::withMessages(['items' => 'No podés anular más de lo que se cargó en la mesa.']);
                }
            }
        }
        $quoteItems = [];
        foreach ($totals['p'] as $id => $quantity) {
            if ($quantity > 0) {
                $quoteItems[] = ['product_id' => (int) $id, 'quantity' => $quantity];
            }
        }
        $quoteAddons = [];
        foreach ($totals['a'] as $id => $quantity) {
            if ($quantity > 0) {
                $quoteAddons[] = ['addon_id' => (int) $id, 'quantity' => $quantity];
            }
        }
        if ($quoteItems === []) {
            throw ValidationException::withMessages(['items' => $quoteAddons === [] && $kind === TableRoundKind::Void
                ? 'Para anular toda la mesa usá "Cancelar mesa".'
                : 'La mesa necesita al menos un producto.']);
        }

        $order = Order::query()->lockForUpdate()->findOrFail($session->order_id);
        if ($order->payment_status === PaymentStatus::Paid || $order->status->isClosed()) {
            throw ValidationException::withMessages(['table' => 'La cuenta de esta mesa ya no admite cambios.']);
        }
        $quote = $this->pricing->quote($quoteItems, OrderCondition::Cooked, $quoteAddons, DeliveryType::DineIn);
        $before = (int) $order->total;
        $order->update([...$this->quotes->totals($quote), 'edit_version' => $order->edit_version + 1]);
        $this->quotes->execute($order, $quote, true);
        $order->revisions()->create([
            'admin_id' => $actor->id, 'order_status' => $order->status->value,
            'total_before' => $before, 'total_after' => (int) $order->total,
            'changed_fields' => ['fields' => ['items'], 'economic_change' => true], 'reason' => null, 'created_at' => now(),
        ]);

        return $names;
    }

    /** @return array<string, string> */
    private function snapshotNames(int $orderId): array
    {
        $names = [];
        foreach (DB::table('order_items')->where('order_id', $orderId)->whereNotNull('product_id')->get(['product_id', 'product_name']) as $item) {
            $names['p'.$item->product_id] = (string) $item->product_name;
        }
        foreach (DB::table('order_toppings')->where('order_id', $orderId)->whereNotNull('order_addon_id')->get(['order_addon_id', 'name']) as $topping) {
            $names['a'.$topping->order_addon_id] = (string) $topping->name;
        }

        return $names;
    }

    /**
     * @param  array<int, int>  $products
     * @param  array<int, int>  $extras
     */
    private function orderData(array $products, array $extras): OrderCheckoutData
    {
        return new OrderCheckoutData(
            items: array_map(fn (int $id, int $quantity): array => ['product_id' => $id, 'quantity' => $quantity], array_keys($products), $products),
            addons: array_map(fn (int $id, int $quantity): array => ['addon_id' => $id, 'quantity' => $quantity], array_keys($extras), $extras),
            condition: OrderCondition::Cooked, deliveryType: DeliveryType::DineIn,
            deliveryPeriod: null, deliveryDate: null, deliveryTime: null, deliveryAddress: null, notes: null,
            customerId: null, customerName: null, customerPhone: null, customerPhoneNormalized: null, customerAddress: null,
            paymentStatus: PaymentStatus::Unpaid,
        );
    }

    private function replay(string $hash, string $fingerprint): ?TableRound
    {
        $round = TableRound::query()->where('restaurant_id', $this->restaurant->id())->where('idempotency_hash', $hash)->first();
        if ($round !== null && $round->fingerprint !== $fingerprint) {
            throw ValidationException::withMessages(['round_key' => 'Esta ronda ya fue confirmada con otro contenido.']);
        }

        return $round?->load(['items', 'session.order', 'session.waiter']);
    }

    /**
     * @param  array<int, array<string, mixed>>  $lines
     * @return array<int, int> quantity by id, sorted by id
     */
    private function lines(array $lines, string $idKey, string $field): array
    {
        $quantities = [];
        foreach ($lines as $line) {
            $id = $line[$idKey] ?? null;
            $quantity = $line['quantity'] ?? null;
            if (! is_int($id) || ! is_int($quantity) || $quantity < 1 || $quantity > 120 || isset($quantities[$id])) {
                throw ValidationException::withMessages([$field => 'Cada producto va una sola vez por ronda, con una cantidad entre 1 y 120.']);
            }
            $quantities[$id] = $quantity;
        }
        ksort($quantities);

        return $quantities;
    }
}
