<?php

namespace App\Modules\Orders\Http\Controllers;

use App\Modules\Customers\Models\Customer;
use App\Modules\Orders\Actions\BuildOrderDashboardData;
use App\Modules\Orders\Actions\CreateOrder;
use App\Modules\Orders\Data\OrderCheckoutData;
use App\Modules\Orders\Enums\OrderSource;
use App\Modules\Orders\Enums\OrderStatus;
use App\Modules\Orders\Http\Requests\StoreAdminOrderRequest;
use App\Modules\Orders\Http\Resources\AdminOrderResource;
use App\Modules\Orders\Models\Order;
use App\Modules\Orders\Models\OrderAddon;
use App\Modules\Orders\Support\OrderSummaryFormatter;
use App\Modules\Products\Models\Product;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

final class OrderManagementController
{
    public function index(
        Request $request,
        OrderSummaryFormatter $formatter,
        BuildOrderDashboardData $dashboardData,
    ): Response {
        Gate::authorize('viewAny', Order::class);
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:160'],
            'status' => ['nullable', Rule::enum(OrderStatus::class)],
            'date' => ['nullable', 'date_format:Y-m-d'],
            'order' => ['nullable', 'string', 'max:32', 'regex:/^TP-[0-9]+$/'],
        ]);
        $search = trim((string) ($filters['search'] ?? ''));
        $orders = Order::query()->with('customer:id,name')
            ->when($search !== '', fn ($query) => $query->where(fn ($query) => $query
                ->where('public_id', 'ilike', "%{$search}%")
                ->orWhere('customer_name_snapshot', 'ilike', "%{$search}%")))
            ->when(isset($filters['status']), fn ($query) => $query->where('status', $filters['status']))
            ->when(isset($filters['date']), fn ($query) => $query->whereDate('delivery_date', $filters['date']))
            ->orderByDesc('delivery_date')->orderByDesc('id')->paginate(25)->withQueryString();

        $selectedOrder = isset($filters['order'])
            ? Order::query()->where('public_id', $filters['order'])->firstOrFail()
            : null;

        if ($selectedOrder !== null) {
            Gate::authorize('view', $selectedOrder);
            $selectedOrder->load(['items.compositions', 'toppings']);
        }

        return Inertia::render('admin/orders/index', [
            'orders' => $orders->through(fn (Order $order) => (new AdminOrderResource($order))->resolve($request)),
            'filters' => ['search' => $search, 'status' => $filters['status'] ?? null, 'date' => $filters['date'] ?? null],
            'selectedOrder' => $selectedOrder === null ? null : (new AdminOrderResource($selectedOrder))->resolve($request),
            'selectedOrderSummary' => $selectedOrder === null ? null : $formatter->format($selectedOrder),
            ...$dashboardData->execute(),
        ]);
    }

    public function create(): Response
    {
        Gate::authorize('create', Order::class);

        return Inertia::render('admin/orders/create', [
            'addons' => OrderAddon::query()->where('is_available', true)->orderBy('sort_order')->get(['id', 'name', 'price']),
            'products' => Product::query()->where('is_available', true)->orderBy('name')->get(['id', 'name', 'unit_price', 'half_dozen_price', 'dozen_price']),
            'customers' => Customer::query()->orderBy('name')->limit(100)->get(['id', 'name', 'phone', 'address']),
        ]);
    }

    public function store(StoreAdminOrderRequest $request, CreateOrder $createOrder): RedirectResponse
    {
        $order = $createOrder->execute(OrderCheckoutData::fromRequest($request, false), OrderSource::Admin, (int) $request->user()->getAuthIdentifier());

        return to_route('admin.orders.show', $order)->with('success', 'Order created.');
    }

    public function show(Request $request, Order $order, OrderSummaryFormatter $formatter): Response
    {
        Gate::authorize('view', $order);
        $order->load(['items.compositions', 'toppings']);

        return Inertia::render('admin/orders/show', ['order' => (new AdminOrderResource($order))->resolve($request), 'summary' => $formatter->format($order)]);
    }

    public function destroy(Request $request, Order $order): RedirectResponse
    {
        Gate::authorize('delete', $order);
        $order->delete();
        Log::info('[Orders] order.deleted', ['module' => 'Orders', 'order_id' => $order->public_id, 'admin_id' => $request->user()->getAuthIdentifier()]);

        return to_route('admin.orders.index')->with('success', 'Order removed.');
    }
}
