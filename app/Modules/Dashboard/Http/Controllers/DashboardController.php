<?php

namespace App\Modules\Dashboard\Http\Controllers;

use App\Modules\Dashboard\Enums\DashboardPeriod;
use App\Modules\Dashboard\Queries\BuildOperationalDashboard;
use App\Modules\Marketing\Queries\BuildMarketingDashboardSummary;
use App\Modules\Orders\Http\Resources\AdminOrderResource;
use App\Modules\Orders\Models\Order;
use App\Modules\Orders\Support\OrderSummaryFormatter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

final class DashboardController
{
    public function __invoke(
        Request $request,
        BuildOperationalDashboard $dashboard,
        BuildMarketingDashboardSummary $marketingSummary,
        OrderSummaryFormatter $formatter,
    ): Response {
        Gate::authorize('viewAny', Order::class);

        $filters = $request->validate([
            'period' => ['nullable', Rule::enum(DashboardPeriod::class)],
            'order' => ['nullable', 'string', 'max:32', 'regex:/^TP-[0-9]+$/'],
        ]);
        $period = DashboardPeriod::from($filters['period'] ?? DashboardPeriod::Today->value);
        $selectedOrder = isset($filters['order'])
            ? Order::query()->where('public_id', $filters['order'])->firstOrFail()
            : null;

        if ($selectedOrder !== null) {
            Gate::authorize('view', $selectedOrder);
            $selectedOrder->load(['items.compositions', 'toppings']);
        }

        return Inertia::render('admin/dashboard', [
            'dashboard' => fn (): array => $dashboard->execute($period),
            'marketingSummary' => fn (): array => $marketingSummary->execute(),
            'selectedOrder' => $selectedOrder === null
                ? null
                : (new AdminOrderResource($selectedOrder))->resolve($request),
            'selectedOrderSummary' => $selectedOrder === null
                ? null
                : $formatter->formatAdmin($selectedOrder),
        ]);
    }
}
