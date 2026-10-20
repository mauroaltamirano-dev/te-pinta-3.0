<?php

namespace App\Modules\Orders\Http\Controllers;

use App\Modules\Orders\Actions\BuildAdminOrderEditData;
use App\Modules\Orders\Actions\BuildAdminOrderFormOptions;
use App\Modules\Orders\Actions\BuildOrderDashboardData;
use App\Modules\Orders\Actions\CreateOrder;
use App\Modules\Orders\Actions\UpdateOrder;
use App\Modules\Orders\Data\OrderCheckoutData;
use App\Modules\Orders\Enums\OrderSource;
use App\Modules\Orders\Enums\OrderStatus;
use App\Modules\Orders\Http\Requests\StoreAdminOrderRequest;
use App\Modules\Orders\Http\Requests\UpdateAdminOrderRequest;
use App\Modules\Orders\Http\Resources\AdminOrderResource;
use App\Modules\Orders\Models\Order;
use App\Modules\Orders\Support\OpenTableOrders;
use App\Modules\Orders\Support\OrderSummaryFormatter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

final class OrderManagementController
{
    public function home(Request $request, BuildAdminOrderFormOptions $options): Response|RedirectResponse
    {
        if ($request->user()?->can('create', Order::class) !== true) {
            return to_route('admin.orders.index');
        }

        return Inertia::render('admin/home', $options->execute());
    }

    public function index(
        Request $request,
        OrderSummaryFormatter $formatter,
        BuildOrderDashboardData $dashboardData,
    ): Response {
        Gate::authorize('viewAny', Order::class);
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:160'],
            'status' => ['nullable', Rule::enum(OrderStatus::class)],
            'group' => ['nullable', Rule::in(['all', 'in_progress', 'delivered', 'cancelled'])],
            'date' => ['nullable', 'date_format:Y-m-d'],
            'sort' => ['nullable', Rule::in(['delivery_date', 'customer', 'total', 'id'])],
            'direction' => ['nullable', Rule::in(['asc', 'desc'])],
            'view' => ['nullable', Rule::in(['grid', 'list'])],
            'order' => ['nullable', 'string', 'max:32', 'regex:/^TP-[0-9]+$/'],
        ]);
        $search = trim((string) ($filters['search'] ?? ''));
        $status = $filters['status'] ?? null;
        $group = $status === null
            ? ($filters['group'] ?? 'all')
            : match ($status) {
                OrderStatus::Delivered->value => 'delivered',
                OrderStatus::Cancelled->value => 'cancelled',
                default => 'in_progress',
            };
        $sort = $filters['sort'] ?? 'delivery_date';
        $direction = $filters['direction'] ?? 'desc';
        $sortColumn = match ($sort) {
            'customer' => 'customer_name_snapshot',
            'total' => 'total',
            'id' => 'id',
            default => 'delivery_date',
        };
        $orders = Order::query()->with([
            'items.compositions',
            'toppings',
        ])
            ->when($search !== '', fn ($query) => $query->where(fn ($query) => $query
                ->where('public_id', 'ilike', "%{$search}%")
                ->orWhere('customer_name_snapshot', 'ilike', "%{$search}%")))
            ->when($status !== null, fn ($query) => $query->where('status', $status))
            ->when($status === null && $group === 'all', fn ($query) => $query->where('status', '!=', OrderStatus::Cancelled))
            ->when($status === null && $group === 'in_progress', fn ($query) => $query->whereIn('status', [
                OrderStatus::PendingConfirmation,
                OrderStatus::Waiting,
                OrderStatus::Prepared,
            ]))
            ->when($status === null && $group === 'delivered', fn ($query) => $query->where('status', OrderStatus::Delivered))
            ->when($status === null && $group === 'cancelled', fn ($query) => $query->where('status', OrderStatus::Cancelled))
            ->when(isset($filters['date']), fn ($query) => $query->whereDate('delivery_date', $filters['date']))
            ->orderBy($sortColumn, $direction)
            ->when($sortColumn !== 'id', fn ($query) => $query->orderBy('id', $direction))
            ->paginate(25)
            ->withQueryString();

        $selectedOrder = isset($filters['order'])
            ? Order::query()->where('public_id', $filters['order'])->firstOrFail()
            : null;

        if ($selectedOrder !== null) {
            Gate::authorize('view', $selectedOrder);
            $selectedOrder->load(['items.compositions', 'toppings']);
        }

        return Inertia::render('admin/orders/index', [
            'orders' => $orders->through(fn (Order $order) => (new AdminOrderResource($order))->resolve($request)),
            'filters' => [
                'search' => $search,
                'status' => $status,
                'group' => $group,
                'date' => $filters['date'] ?? null,
                'sort' => $sort,
                'direction' => $direction,
                'view' => $filters['view'] ?? 'grid',
            ],
            'selectedOrder' => $selectedOrder === null ? null : (new AdminOrderResource($selectedOrder))->resolve($request),
            'selectedOrderSummary' => $selectedOrder === null ? null : $formatter->formatAdmin($selectedOrder),
            ...$dashboardData->execute(),
        ]);
    }

    public function create(BuildAdminOrderFormOptions $options): Response
    {
        Gate::authorize('create', Order::class);

        return Inertia::render('admin/orders/create', $options->execute());
    }

    public function store(StoreAdminOrderRequest $request, CreateOrder $createOrder, OrderSummaryFormatter $formatter): RedirectResponse|JsonResponse
    {
        $order = $createOrder->execute(OrderCheckoutData::fromRequest($request, false), OrderSource::Admin, (int) $request->user()->getAuthIdentifier());

        if ($request->expectsJson()) {
            return response()->json([
                'data' => [
                    'order_id' => $order->public_id,
                    'status' => $order->status->value,
                    'payment_status' => $order->payment_status->value,
                    'payment_method' => $order->payment_method?->value,
                    'discount_type' => $order->discount_type?->value,
                    'discount_value' => $order->discount_value,
                    'discount_amount' => (int) $order->discount_amount,
                    'total' => (int) $order->total,
                    'summary' => $formatter->formatAdmin($order),
                ],
            ], 201);
        }

        return to_route('admin.orders.show', $order)->with('success', 'Pedido creado.');
    }

    public function show(Request $request, Order $order, OrderSummaryFormatter $formatter): Response
    {
        Gate::authorize('view', $order);
        $order->load(['items.compositions', 'toppings']);

        return Inertia::render('admin/orders/show', ['order' => (new AdminOrderResource($order))->resolve($request), 'summary' => $formatter->formatAdmin($order)]);
    }

    public function edit(Request $request, Order $order, BuildAdminOrderEditData $editData, BuildAdminOrderFormOptions $options): Response
    {
        Gate::authorize('update', $order);
        $grant = $request->session()->get(EnableClosedOrderEditController::sessionKey($order));
        $granted = is_array($grant)
            && ($grant['admin_id'] ?? null) === (int) $request->user()->getAuthIdentifier()
            && ($grant['version'] ?? null) === (int) $order->edit_version;

        return Inertia::render('admin/orders/edit', [
            'order' => $editData->execute($order, $granted),
            ...$options->execute($order),
        ]);
    }

    public function update(UpdateAdminOrderRequest $request, Order $order, UpdateOrder $update): RedirectResponse|JsonResponse
    {
        $grantKey = EnableClosedOrderEditController::sessionKey($order);
        $grant = $request->session()->get($grantKey);
        $adminId = (int) $request->user()->getAuthIdentifier();
        $expectedVersion = (int) $request->validated('expected_version');
        $closedReason = is_array($grant)
            && ($grant['admin_id'] ?? null) === $adminId
            && ($grant['version'] ?? null) === $expectedVersion
            ? ($grant['reason'] ?? null)
            : null;
        $updated = $update->execute(
            $order,
            OrderCheckoutData::fromRequest($request, false),
            OrderStatus::from($request->validated('status')),
            $expectedVersion,
            $adminId,
            is_string($closedReason) ? $closedReason : null,
        );
        $request->session()->forget($grantKey);

        if ($request->expectsJson()) {
            return response()->json(['data' => [
                'order_id' => $updated->public_id,
                'status' => $updated->status->value,
                'payment_status' => $updated->payment_status->value,
                'payment_method' => $updated->payment_method?->value,
                'discount_type' => $updated->discount_type?->value,
                'discount_value' => $updated->discount_value,
                'discount_amount' => (int) $updated->discount_amount,
                'total' => (int) $updated->total,
                'version' => (int) $updated->edit_version,
            ]]);
        }

        return to_route('admin.orders.show', $updated)->with('success', 'Pedido actualizado.');
    }

    public function destroy(Request $request, Order $order): RedirectResponse
    {
        Gate::authorize('delete', $order);
        DB::transaction(function () use ($order): void {
            $locked = Order::query()->lockForUpdate()->findOrFail($order->id);
            app(OpenTableOrders::class)->assertNotOpen($locked->id);
            $locked->delete();
        });
        Log::info('[Orders] order.deleted', ['module' => 'Orders', 'order_id' => $order->public_id, 'admin_id' => $request->user()->getAuthIdentifier()]);

        return to_route('admin.orders.index')->with('success', 'Pedido enviado a la papelera.');
    }
}
