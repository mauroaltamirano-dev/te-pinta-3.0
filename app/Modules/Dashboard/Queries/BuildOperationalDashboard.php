<?php

namespace App\Modules\Dashboard\Queries;

use App\Modules\Dashboard\Enums\DashboardPeriod;
use App\Modules\Orders\Enums\DeliveryPeriod;
use App\Modules\Orders\Enums\DeliveryType;
use App\Modules\Orders\Enums\OrderCondition;
use App\Modules\Orders\Enums\OrderStatus;
use App\Modules\Orders\Support\OrderBusinessDate;
use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use stdClass;

final readonly class BuildOperationalDashboard
{
    /** @var array<int, string> */
    private const CONFIRMED_STATUSES = [
        OrderStatus::Waiting->value,
        OrderStatus::Prepared->value,
        OrderStatus::Delivered->value,
    ];

    /** @var array<int, string> */
    private const ACTIVE_STATUSES = [
        OrderStatus::PendingConfirmation->value,
        ...self::CONFIRMED_STATUSES,
    ];

    /** @var array<int, string> */
    private const URGENT_STATUSES = [
        OrderStatus::PendingConfirmation->value,
        OrderStatus::Waiting->value,
        OrderStatus::Prepared->value,
    ];

    public function __construct(private OrderBusinessDate $businessDate) {}

    /** @return array<string, mixed> */
    public function execute(DashboardPeriod $period): array
    {
        $today = $this->businessDate->today();
        [$start, $end] = $period->range($today);
        $todayDate = $today->toDateString();
        $summary = $this->periodSummary(
            $start?->toDateString(),
            $end?->toDateString(),
        );
        $start ??= CarbonImmutable::parse($summary->start_date ?? $todayDate);
        $end ??= CarbonImmutable::parse($summary->end_date ?? $todayDate);
        $startDate = $start->toDateString();
        $endDate = $end->toDateString();

        $todayStatusCounts = $this->todayStatusCounts($todayDate);
        $conditionCounts = $this->conditionUnitCounts($startDate, $endDate);

        return [
            'period' => $period->value,
            'range' => [
                'start_date' => $startDate,
                'end_date' => $endDate,
            ],
            'kpis' => [
                'sales_total' => (int) $summary->sales_total,
                'collected_total' => (int) $summary->collected_total,
                'orders_count' => (int) $summary->orders_count,
                'pending_confirmation_count' => $todayStatusCounts[OrderStatus::PendingConfirmation->value] ?? 0,
                'unpaid_count' => (int) $summary->unpaid_count,
                'empanadas_count' => array_sum($conditionCounts),
            ],
            'today_status_distribution' => array_map(
                fn (OrderStatus $status): array => [
                    'status' => $status->value,
                    'orders_count' => $todayStatusCounts[$status->value] ?? 0,
                ],
                [
                    OrderStatus::PendingConfirmation,
                    OrderStatus::Waiting,
                    OrderStatus::Prepared,
                    OrderStatus::Delivered,
                ],
            ),
            'urgent_orders' => $this->urgentOrders($todayDate),
            'today_orders' => $this->todayOrders($todayDate),
            'top_products' => $this->topProducts($startDate, $endDate),
            'evolution' => $this->evolution($period, $start, $end),
            'delivery_distribution' => [
                [
                    'type' => DeliveryType::Delivery->value,
                    'orders_count' => (int) $summary->delivery_orders_count,
                ],
                [
                    'type' => DeliveryType::Pickup->value,
                    'orders_count' => (int) $summary->pickup_orders_count,
                ],
            ],
            'condition_distribution' => array_map(
                fn (OrderCondition $condition): array => [
                    'condition' => $condition->value,
                    'units_count' => $conditionCounts[$condition->value] ?? 0,
                ],
                OrderCondition::cases(),
            ),
        ];
    }

    private function periodSummary(?string $startDate, ?string $endDate): stdClass
    {
        return DB::table('orders')
            ->whereNull('deleted_at')
            ->when(
                $startDate !== null && $endDate !== null,
                fn (Builder $query) => $query->whereBetween('delivery_date', [$startDate, $endDate]),
            )
            ->selectRaw(<<<'SQL'
                COALESCE(SUM(total) FILTER (WHERE status IN ('waiting', 'prepared', 'delivered')), 0) AS sales_total,
                COALESCE(SUM(total) FILTER (WHERE status IN ('waiting', 'prepared', 'delivered') AND payment_status = 'paid'), 0) AS collected_total,
                COUNT(*) FILTER (WHERE status IN ('pending_confirmation', 'waiting', 'prepared', 'delivered')) AS orders_count,
                COUNT(*) FILTER (WHERE status IN ('waiting', 'prepared', 'delivered') AND payment_status = 'unpaid') AS unpaid_count,
                COUNT(*) FILTER (WHERE status IN ('pending_confirmation', 'waiting', 'prepared', 'delivered') AND delivery_type = 'delivery') AS delivery_orders_count,
                COUNT(*) FILTER (WHERE status IN ('pending_confirmation', 'waiting', 'prepared', 'delivered') AND delivery_type = 'pickup') AS pickup_orders_count,
                MIN(delivery_date) FILTER (WHERE status IN ('pending_confirmation', 'waiting', 'prepared', 'delivered')) AS start_date,
                MAX(delivery_date) FILTER (WHERE status IN ('pending_confirmation', 'waiting', 'prepared', 'delivered')) AS end_date
                SQL)
            ->firstOrFail();
    }

    /** @return array<string, int> */
    private function todayStatusCounts(string $today): array
    {
        return DB::table('orders')
            ->whereNull('deleted_at')
            ->where('delivery_date', $today)
            ->whereIn('status', self::ACTIVE_STATUSES)
            ->selectRaw('status, COUNT(*) AS orders_count')
            ->groupBy('status')
            ->pluck('orders_count', 'status')
            ->map(fn (mixed $count): int => (int) $count)
            ->all();
    }

    /** @return array<string, int> */
    private function conditionUnitCounts(string $startDate, string $endDate): array
    {
        return DB::table('order_items')
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->whereNull('orders.deleted_at')
            ->whereBetween('orders.delivery_date', [$startDate, $endDate])
            ->whereIn('orders.status', self::CONFIRMED_STATUSES)
            ->selectRaw('orders.condition, COALESCE(SUM(order_items.units_count), 0) AS units_count')
            ->groupBy('orders.condition')
            ->pluck('units_count', 'condition')
            ->map(fn (mixed $count): int => (int) $count)
            ->all();
    }

    /** @return array<int, array<string, mixed>> */
    private function urgentOrders(string $today): array
    {
        return $this->todayOrdersQuery($today)
            ->whereIn('status', self::URGENT_STATUSES)
            ->orderByRaw("CASE status WHEN 'pending_confirmation' THEN 0 WHEN 'waiting' THEN 1 ELSE 2 END")
            ->orderByRaw("CASE delivery_period WHEN 'midday' THEN 0 WHEN 'afternoon' THEN 1 ELSE 2 END")
            ->orderByRaw('delivery_time ASC NULLS LAST')
            ->orderBy('id')
            ->get()
            ->map(fn (stdClass $order): array => $this->orderSummary($order))
            ->all();
    }

    /** @return array<int, array<string, mixed>> */
    private function todayOrders(string $today): array
    {
        return $this->todayOrdersQuery($today)
            ->whereIn('status', self::ACTIVE_STATUSES)
            ->orderByRaw("CASE delivery_period WHEN 'midday' THEN 0 WHEN 'afternoon' THEN 1 ELSE 2 END")
            ->orderByRaw('delivery_time ASC NULLS LAST')
            ->orderByRaw("CASE status WHEN 'pending_confirmation' THEN 0 WHEN 'waiting' THEN 1 WHEN 'prepared' THEN 2 ELSE 3 END")
            ->orderBy('id')
            ->get()
            ->map(fn (stdClass $order): array => $this->orderSummary($order))
            ->all();
    }

    private function todayOrdersQuery(string $today): Builder
    {
        return DB::table('orders')
            ->whereNull('deleted_at')
            ->where('delivery_date', $today)
            ->select([
                'public_id',
                'customer_name_snapshot',
                'delivery_period',
                'delivery_time',
                'delivery_type',
                'status',
                'payment_status',
                'total',
                'id',
            ]);
    }

    /** @return array<string, mixed> */
    private function orderSummary(stdClass $order): array
    {
        return [
            'id' => (string) $order->public_id,
            'customer_name' => (string) $order->customer_name_snapshot,
            'delivery_period' => (string) $order->delivery_period,
            'delivery_time' => $order->delivery_time === null ? null : (string) $order->delivery_time,
            'delivery_type' => (string) $order->delivery_type,
            'status' => (string) $order->status,
            'payment_status' => (string) $order->payment_status,
            'total' => (int) $order->total,
        ];
    }

    /** @return array<int, array{product_id: int, name: string, units_count: int}> */
    private function topProducts(string $startDate, string $endDate): array
    {
        $directItems = DB::table('order_items AS items')
            ->join('orders', 'orders.id', '=', 'items.order_id')
            ->whereNull('orders.deleted_at')
            ->whereBetween('orders.delivery_date', [$startDate, $endDate])
            ->whereIn('orders.status', self::CONFIRMED_STATUSES)
            ->whereNotNull('items.product_id')
            ->selectRaw('items.product_id, items.product_name, items.order_id, items.units_count');

        $composedItems = DB::table('order_item_compositions AS compositions')
            ->join('order_items AS items', 'items.id', '=', 'compositions.order_item_id')
            ->join('orders', 'orders.id', '=', 'items.order_id')
            ->whereNull('orders.deleted_at')
            ->whereBetween('orders.delivery_date', [$startDate, $endDate])
            ->whereIn('orders.status', self::CONFIRMED_STATUSES)
            ->selectRaw('compositions.product_id, compositions.product_name, items.order_id, compositions.quantity AS units_count');

        $rankedItems = DB::query()
            ->fromSub($directItems->unionAll($composedItems), 'product_units')
            ->selectRaw('product_id, product_name, units_count, ROW_NUMBER() OVER (PARTITION BY product_id ORDER BY order_id DESC, product_name ASC) AS name_rank');

        return DB::query()
            ->fromSub($rankedItems, 'ranked_product_units')
            ->selectRaw('product_id, MAX(product_name) FILTER (WHERE name_rank = 1) AS name, SUM(units_count) AS units_count')
            ->groupBy('product_id')
            ->orderByDesc('units_count')
            ->orderBy('name')
            ->orderBy('product_id')
            ->get()
            ->map(fn (stdClass $product): array => [
                'product_id' => (int) $product->product_id,
                'name' => (string) $product->name,
                'units_count' => (int) $product->units_count,
            ])
            ->all();
    }

    /** @return array<int, array{bucket: string, total: int}> */
    private function evolution(
        DashboardPeriod $period,
        CarbonImmutable $start,
        CarbonImmutable $end,
    ): array {
        if ($period === DashboardPeriod::Today) {
            $totals = DB::table('orders')
                ->whereNull('deleted_at')
                ->where('delivery_date', $start->toDateString())
                ->whereIn('status', self::CONFIRMED_STATUSES)
                ->selectRaw('delivery_period, COALESCE(SUM(total), 0) AS total')
                ->groupBy('delivery_period')
                ->pluck('total', 'delivery_period');

            return array_map(
                fn (DeliveryPeriod $deliveryPeriod): array => [
                    'bucket' => $deliveryPeriod->value,
                    'total' => (int) ($totals[$deliveryPeriod->value] ?? 0),
                ],
                DeliveryPeriod::cases(),
            );
        }

        $bucketExpression = $period === DashboardPeriod::All
            ? "TO_CHAR(delivery_date, 'YYYY-MM')"
            : 'delivery_date::text';
        $totals = DB::table('orders')
            ->whereNull('deleted_at')
            ->whereBetween('delivery_date', [$start->toDateString(), $end->toDateString()])
            ->whereIn('status', self::CONFIRMED_STATUSES)
            ->selectRaw("{$bucketExpression} AS bucket, COALESCE(SUM(total), 0) AS total")
            ->groupByRaw($bucketExpression)
            ->pluck('total', 'bucket');

        $evolution = [];
        $date = $period === DashboardPeriod::All ? $start->startOfMonth() : $start;
        $lastDate = $period === DashboardPeriod::All ? $end->startOfMonth() : $end;
        for (; $date->lessThanOrEqualTo($lastDate); $date = $period === DashboardPeriod::All ? $date->addMonth() : $date->addDay()) {
            $bucket = $period === DashboardPeriod::All ? $date->format('Y-m') : $date->toDateString();
            $evolution[] = [
                'bucket' => $bucket,
                'total' => (int) ($totals[$bucket] ?? 0),
            ];
        }

        return $evolution;
    }
}
