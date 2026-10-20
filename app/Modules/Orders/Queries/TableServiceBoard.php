<?php

namespace App\Modules\Orders\Queries;

use App\Modules\Identity\Models\User;
use App\Modules\Inventory\Queries\ProductsWithoutConsumption;
use App\Modules\Orders\Actions\BuildAdminOrderFormOptions;
use App\Modules\Orders\Enums\PaymentMethod;
use App\Modules\Orders\Enums\TableRoundKind;
use App\Modules\Orders\Enums\TableSessionStatus;
use App\Modules\Orders\Models\TableRound;
use App\Modules\Orders\Models\TableRoundItem;
use App\Modules\Orders\Models\TableSession;
use App\Modules\Restaurant\Queries\ServiceTables;
use Carbon\CarbonImmutable;
use DateTimeInterface;

/** Read model of the waiter and cashier screens: the tables board and one table with its rounds. */
final readonly class TableServiceBoard
{
    public function __construct(
        private ServiceTables $tables,
        private BuildAdminOrderFormOptions $options,
        private ProductsWithoutConsumption $withoutConsumption,
    ) {}

    /** @return list<array<string, mixed>> */
    public function tables(User $viewer): array
    {
        $sessions = TableSession::query()->where('status', TableSessionStatus::Open)->with(['waiter:id,name', 'order:id,total'])->get()->keyBy('dining_table_id');

        return array_map(fn (array $table): array => [
            ...$table,
            'session' => ($session = $sessions->get($table['id'])) === null ? null : $this->summary($session, $viewer),
        ], $this->tables->listed(array_values($sessions->keys()->map(fn ($id): int => (int) $id)->all())));
    }

    /** @return array<string, mixed>|null null when the table does not exist */
    public function table(int $tableId, User $viewer): ?array
    {
        $table = $this->tables->find($tableId);
        if ($table === null) {
            return null;
        }
        $session = TableSession::query()->where('dining_table_id', $tableId)->where('status', TableSessionStatus::Open)
            ->with(['waiter:id,name', 'order:id,total', 'rounds.items', 'rounds.confirmer:id,name'])->first();
        $cashier = $viewer->canOperateCashier();
        $catalog = $this->options->catalog();

        return [
            'table' => $table,
            'session' => $session === null ? null : [
                ...$this->summary($session, $viewer),
                'can_act' => $session->waiter_id === $viewer->id || $cashier,
                'rounds' => $session->rounds->map(fn (TableRound $round): array => $this->round($round, $session))->all(),
                'lines' => $this->lines($session),
            ],
            'products' => $catalog['products'],
            'addons' => $catalog['addons'],
            'can_cashier' => $cashier,
            'payment_methods' => array_map(fn (PaymentMethod $method): string => $method->value, PaymentMethod::cases()),
            // Charging delivers the order, which Inventory blocks for these products: warn before the money is counted.
            'missing_consumption' => $cashier && $session !== null ? $this->withoutConsumption->names($this->productIds($session)) : [],
        ];
    }

    /** @return array<string, mixed> */
    private function summary(TableSession $session, User $viewer): array
    {
        return [
            'id' => $session->id,
            'waiter' => $session->waiter->name,
            'mine' => $session->waiter_id === $viewer->id,
            'opened_at' => $this->time($session->opened_at),
            'bill_requested' => $session->bill_requested_at !== null,
            'total' => (int) $session->order->total,
        ];
    }

    /** @return array<string, mixed> */
    private function round(TableRound $round, TableSession $session): array
    {
        return [
            'id' => $round->id,
            'sequence' => $round->sequence,
            'kind' => $round->kind->value,
            'time' => $this->time($round->confirmed_at),
            'note' => $round->note,
            // Someone other than the table's waiter: "Cajero Lu".
            'added_by' => $round->confirmed_by === $session->waiter_id ? null : $round->confirmed_by_role->label().' '.$round->confirmer->name,
            'items' => $round->items->map(fn (TableRoundItem $item): array => ['name' => $item->name, 'quantity' => $item->quantity])->all(),
        ];
    }

    /**
     * What the table has now: every round added up, voids subtracted, in first-ordered order.
     *
     * @return list<array{product_id: int|null, addon_id: int|null, name: string, quantity: int}>
     */
    private function lines(TableSession $session): array
    {
        $lines = [];
        foreach ($session->rounds as $round) {
            $sign = $round->kind === TableRoundKind::Void ? -1 : 1;
            foreach ($round->items as $item) {
                $key = $item->product_id !== null ? 'p'.$item->product_id : 'a'.$item->order_addon_id;
                $lines[$key] ??= ['product_id' => $item->product_id, 'addon_id' => $item->order_addon_id, 'name' => $item->name, 'quantity' => 0];
                $lines[$key]['quantity'] += $sign * $item->quantity;
            }
        }

        return array_values(array_filter($lines, fn (array $line): bool => $line['quantity'] > 0));
    }

    /** Wall-clock time of the restaurant, the same the kitchen ticket prints. */
    private function time(DateTimeInterface $moment): string
    {
        return CarbonImmutable::instance($moment)->setTimezone((string) config('orders.timezone'))->format('H:i');
    }

    /** @return list<int> */
    private function productIds(TableSession $session): array
    {
        return array_values($session->rounds->flatMap(fn (TableRound $round) => $round->items->pluck('product_id'))->filter()->unique()->map(fn ($id): int => (int) $id)->all());
    }
}
