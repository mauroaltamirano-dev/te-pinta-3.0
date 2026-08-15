<?php

namespace App\Modules\Customers\Http\Controllers;

use App\Modules\Customers\Actions\RestoreCustomer;
use App\Modules\Customers\Http\Requests\RestoreCustomerRequest;
use App\Modules\Customers\Http\Resources\DeletedCustomerResource;
use App\Modules\Customers\Models\Customer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

final class CustomerTrashController
{
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', Customer::class);
        $customers = Customer::onlyTrashed()
            ->withCount(['orders' => fn ($query) => $query->withTrashed()])
            ->orderByDesc('deleted_at')
            ->orderByDesc('id')
            ->paginate(25)
            ->withQueryString();

        return Inertia::render('admin/customers/trash', [
            'customers' => $customers->through(
                fn (Customer $customer): array => (new DeletedCustomerResource($customer))->resolve($request),
            ),
        ]);
    }

    public function restore(RestoreCustomerRequest $request, Customer $customer, RestoreCustomer $restore): RedirectResponse
    {
        $restore->execute($customer, (int) $request->user()->getAuthIdentifier());

        return back()->with('success', 'Customer restored.');
    }
}
