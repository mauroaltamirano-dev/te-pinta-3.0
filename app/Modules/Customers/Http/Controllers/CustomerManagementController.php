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
use App\Modules\Orders\Http\Resources\AdminOrderResource;
use App\Modules\Orders\Support\OrderSummaryFormatter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

final class CustomerManagementController
{
    /** @var array<string, string> */
    private const SORT_COLUMNS = [
        'name' => 'name',
        'orders_count' => 'orders_count',
        'historical_spend' => 'effective_orders_sum_total',
    ];

    public function index(Request $request, PhoneNormalizer $phoneNormalizer): Response
    {
        Gate::authorize('viewAny', Customer::class);

        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:160'],
            'sort' => ['nullable', Rule::in(array_keys(self::SORT_COLUMNS))],
            'direction' => ['nullable', Rule::in(['asc', 'desc'])],
        ]);
        $search = trim((string) ($filters['search'] ?? ''));
        $sort = (string) ($filters['sort'] ?? 'name');
        $direction = ($filters['direction'] ?? null) === 'desc' ? 'desc' : 'asc';
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
            ->withCount('orders')
            ->with('latestOrder')
            ->withSum(['orders as effective_orders_sum_total' => fn ($query) => $query->where('status', '!=', 'cancelled')], 'total')
            ->when(
                $sort === 'historical_spend',
                fn ($query) => $query->orderByRaw(
                    $direction === 'desc'
                        ? 'effective_orders_sum_total DESC NULLS LAST'
                        : 'effective_orders_sum_total ASC NULLS FIRST'
                ),
                fn ($query) => $query->orderBy(self::SORT_COLUMNS[$sort], $direction),
            )
            ->orderBy('id')
            ->paginate(25)
            ->withQueryString();

        return Inertia::render('admin/customers/index', [
            'customers' => $customers->through(fn (Customer $customer): array => (new AdminCustomerResource($customer))->resolve($request)),
            'filters' => [
                'search' => $search,
                'sort' => $sort,
                'direction' => $direction,
            ],
        ]);
    }

    public function show(Request $request, Customer $customer, OrderSummaryFormatter $formatter): Response
    {
        Gate::authorize('view', $customer);

        $filters = $request->validate([
            'order' => ['nullable', 'string', 'max:32', 'regex:/^TP-[0-9]+$/'],
        ]);

        $customer->load([
            'orders' => fn ($query) => $query
                ->orderByDesc('delivery_date')
                ->orderByDesc('id'),
            'latestOrder',
        ])
            ->loadCount('orders')
            ->loadSum(['orders as effective_orders_sum_total' => fn ($query) => $query->where('status', '!=', 'cancelled')], 'total');

        $selectedOrder = isset($filters['order'])
            ? $customer->orders()->where('public_id', $filters['order'])->firstOrFail()
            : null;

        if ($selectedOrder !== null) {
            Gate::authorize('view', $selectedOrder);
            $selectedOrder->load(['items.compositions', 'toppings']);
        }

        return Inertia::render('admin/customers/show', [
            'customer' => (new AdminCustomerResource($customer))->resolve($request),
            'selectedOrder' => $selectedOrder === null ? null : (new AdminOrderResource($selectedOrder))->resolve($request),
            'selectedOrderSummary' => $selectedOrder === null ? null : $formatter->formatAdmin($selectedOrder),
        ]);
    }

    public function store(StoreCustomerRequest $request, CreateCustomer $createCustomer): RedirectResponse
    {
        $customer = $createCustomer->execute(CustomerData::fromRequest($request), (int) $request->user()->getAuthIdentifier());

        return to_route('admin.customers.show', $customer)->with('success', 'Cliente creado.');
    }

    public function update(UpdateCustomerRequest $request, Customer $customer, UpdateCustomer $updateCustomer): RedirectResponse
    {
        $updateCustomer->execute($customer, CustomerData::fromRequest($request), (int) $request->user()->getAuthIdentifier());

        return back()->with('success', 'Cliente actualizado.');
    }

    public function destroy(Request $request, Customer $customer, DeleteCustomer $deleteCustomer): RedirectResponse
    {
        Gate::authorize('delete', $customer);
        $deleteCustomer->execute($customer, (int) $request->user()->getAuthIdentifier());

        return to_route('admin.customers.index')->with('success', 'Cliente enviado a la papelera.');
    }
}
