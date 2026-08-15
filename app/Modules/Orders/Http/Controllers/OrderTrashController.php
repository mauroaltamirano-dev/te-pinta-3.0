<?php

namespace App\Modules\Orders\Http\Controllers;

use App\Modules\Orders\Actions\RestoreOrder;
use App\Modules\Orders\Http\Requests\RestoreOrderRequest;
use App\Modules\Orders\Http\Resources\DeletedOrderResource;
use App\Modules\Orders\Models\Order;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

final class OrderTrashController
{
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', Order::class);
        $orders = Order::onlyTrashed()
            ->orderByDesc('deleted_at')
            ->orderByDesc('id')
            ->paginate(25)
            ->withQueryString();

        return Inertia::render('admin/orders/trash', [
            'orders' => $orders->through(
                fn (Order $order): array => (new DeletedOrderResource($order))->resolve($request),
            ),
        ]);
    }

    public function restore(RestoreOrderRequest $request, Order $order, RestoreOrder $restore): RedirectResponse
    {
        $restore->execute($order, (int) $request->user()->getAuthIdentifier());

        return back()->with('success', 'Order restored.');
    }
}
