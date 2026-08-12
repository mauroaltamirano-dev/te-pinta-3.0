<?php

namespace App\Modules\Customers\Http\Controllers;

use App\Modules\Customers\Actions\CreateCustomer;
use App\Modules\Customers\Actions\DeleteCustomer;
use App\Modules\Customers\Actions\UpdateCustomer;
use App\Modules\Customers\Data\CustomerData;
use App\Modules\Customers\Http\Requests\StoreCustomerRequest;
use App\Modules\Customers\Http\Requests\UpdateCustomerRequest;
use App\Modules\Customers\Http\Resources\AdminCustomerResource;
use App\Modules\Customers\Models\Customer;
use App\Modules\Customers\Support\PhoneNormalizer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

final class CustomerManagementController
{
    public function index(Request $request, PhoneNormalizer $phoneNormalizer): Response
    {
        Gate::authorize('viewAny', Customer::class);

        $search = trim((string) $request->query('search', ''));
        $normalizedPhone = $phoneNormalizer->normalize($search);
        $customers = Customer::query()
            ->when($search !== '', function ($query) use ($search, $normalizedPhone): void {
                $query->where(function ($query) use ($search, $normalizedPhone): void {
                    $query->whereRaw('LOWER(name) LIKE ?', ['%'.mb_strtolower($search).'%']);
                    if ($normalizedPhone !== null) {
                        $query->orWhere('phone_normalized', 'like', '%'.$normalizedPhone.'%');
                    }
                });
            })
            ->withSum(['orders as effective_orders_sum_total' => fn ($query) => $query->where('status', '!=', 'cancelled')], 'total')
            ->orderBy('name')
            ->orderBy('id')
            ->paginate(25)
            ->withQueryString();

        return Inertia::render('admin/customers/index', [
            'customers' => $customers->through(fn (Customer $customer): array => (new AdminCustomerResource($customer))->resolve($request)),
            'filters' => ['search' => $search],
        ]);
    }

    public function show(Request $request, Customer $customer): Response
    {
        Gate::authorize('view', $customer);

        $customer->load(['orders' => fn ($query) => $query
            ->orderByDesc('delivery_date')
            ->orderByDesc('id')])
            ->loadSum(['orders as effective_orders_sum_total' => fn ($query) => $query->where('status', '!=', 'cancelled')], 'total');

        return Inertia::render('admin/customers/show', [
            'customer' => (new AdminCustomerResource($customer))->resolve($request),
        ]);
    }

    public function store(StoreCustomerRequest $request, CreateCustomer $createCustomer): RedirectResponse
    {
        $customer = $createCustomer->execute(CustomerData::fromRequest($request), (int) $request->user()->getAuthIdentifier());

        return to_route('admin.customers.show', $customer)->with('success', 'Customer created.');
    }

    public function update(UpdateCustomerRequest $request, Customer $customer, UpdateCustomer $updateCustomer): RedirectResponse
    {
        $updateCustomer->execute($customer, CustomerData::fromRequest($request), (int) $request->user()->getAuthIdentifier());

        return back()->with('success', 'Customer updated.');
    }

    public function destroy(Request $request, Customer $customer, DeleteCustomer $deleteCustomer): RedirectResponse
    {
        Gate::authorize('delete', $customer);
        $deleteCustomer->execute($customer, (int) $request->user()->getAuthIdentifier());

        return to_route('admin.customers.index')->with('success', 'Customer removed.');
    }
}
