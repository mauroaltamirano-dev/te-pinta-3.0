import { Link } from '@inertiajs/react';
import {
    ArrowDown,
    ArrowUp,
    ArrowUpDown,
    ChevronRight,
    Eye,
    Pencil,
} from 'lucide-react';
import { Button } from '@/components/ui/button';
import {
    formatCustomerCount,
    formatCustomerDate,
    formatCustomerMoney,
} from '@/features/customers/presentation';
import type {
    Customer,
    CustomerSort,
    CustomerSortDirection,
} from '@/features/customers/types';

const sortLabels: Record<CustomerSort, string> = {
    name: 'Nombre',
    orders_count: 'Pedidos',
    historical_spend: 'Gasto histórico',
};

function SortableHeader({
    field,
    sort,
    direction,
    onSort,
}: {
    field: CustomerSort;
    sort: CustomerSort;
    direction: CustomerSortDirection;
    onSort: (field: CustomerSort) => void;
}) {
    const active = field === sort;
    const Icon = active
        ? direction === 'asc'
            ? ArrowUp
            : ArrowDown
        : ArrowUpDown;
    const nextDirection =
        active && direction === 'asc' ? 'descendente' : 'ascendente';

    return (
        <th
            className="px-4 py-3 font-medium"
            scope="col"
            aria-sort={
                active
                    ? direction === 'asc'
                        ? 'ascending'
                        : 'descending'
                    : 'none'
            }
        >
            <button
                type="button"
                onClick={() => onSort(field)}
                aria-label={`Ordenar por ${sortLabels[field]}, ${nextDirection}`}
                className="inline-flex items-center gap-1.5 rounded-sm text-left hover:text-white focus-visible:ring-2 focus-visible:ring-admin-accent focus-visible:outline-none"
            >
                {sortLabels[field]}
                <Icon
                    className={
                        active
                            ? 'size-3.5 text-admin-accent'
                            : 'size-3.5 opacity-55'
                    }
                    aria-hidden="true"
                />
            </button>
        </th>
    );
}

export function CustomerList({
    customers,
    onEdit,
    sort,
    direction,
    onSort,
}: {
    customers: Customer[];
    onEdit: (customer: Customer) => void;
    sort: CustomerSort;
    direction: CustomerSortDirection;
    onSort: (field: CustomerSort) => void;
}) {
    return (
        <>
            <div className="hidden overflow-x-auto md:block">
                <table className="w-full text-left text-sm">
                    <thead className="border-b border-admin-border text-admin-text-tertiary">
                        <tr>
                            <SortableHeader
                                field="name"
                                sort={sort}
                                direction={direction}
                                onSort={onSort}
                            />
                            <th className="px-4 py-3 font-medium" scope="col">
                                Teléfono
                            </th>
                            <th className="px-4 py-3 font-medium" scope="col">
                                Último pedido
                            </th>
                            <SortableHeader
                                field="orders_count"
                                sort={sort}
                                direction={direction}
                                onSort={onSort}
                            />
                            <SortableHeader
                                field="historical_spend"
                                sort={sort}
                                direction={direction}
                                onSort={onSort}
                            />
                            <th
                                className="px-4 py-3 text-right font-medium"
                                scope="col"
                            >
                                Acciones
                            </th>
                        </tr>
                    </thead>
                    <tbody className="divide-y divide-admin-border">
                        {customers.map((customer) => (
                            <tr key={customer.id} className="group">
                                <td className="px-4 py-4 font-medium text-white">
                                    <Link
                                        className="underline-offset-4 group-hover:text-admin-accent hover:underline"
                                        href={`/admin/customers/${customer.id}`}
                                    >
                                        {customer.name}
                                    </Link>
                                </td>
                                <td className="px-4 py-4 text-admin-text-secondary">
                                    {customer.phone ?? 'Sin teléfono'}
                                </td>
                                <td className="px-4 py-4 text-admin-text-secondary tabular-nums">
                                    {formatCustomerDate(
                                        customer.latest_order_date,
                                    )}
                                </td>
                                <td className="px-4 py-4 text-admin-text-secondary tabular-nums">
                                    {customer.orders_count}
                                </td>
                                <td className="px-4 py-4 font-medium text-white tabular-nums">
                                    {customer.orders_count === 0
                                        ? 'Sin compras'
                                        : formatCustomerMoney.format(
                                              customer.historical_spend,
                                          )}
                                </td>
                                <td className="px-4 py-3">
                                    <div className="flex justify-end gap-1">
                                        <Button
                                            asChild
                                            variant="ghost"
                                            size="icon"
                                        >
                                            <Link
                                                href={`/admin/customers/${customer.id}`}
                                                aria-label={`Ver cliente ${customer.name}`}
                                            >
                                                <Eye />
                                            </Link>
                                        </Button>
                                        <Button
                                            type="button"
                                            variant="ghost"
                                            size="icon"
                                            aria-label={`Editar cliente ${customer.name}`}
                                            onClick={() => onEdit(customer)}
                                        >
                                            <Pencil />
                                        </Button>
                                    </div>
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>

            <div className="divide-y divide-admin-border md:hidden">
                {customers.map((customer) => (
                    <article
                        key={customer.id}
                        className="grid grid-cols-[1fr_auto] gap-3 px-4 py-4"
                    >
                        <Link
                            href={`/admin/customers/${customer.id}`}
                            className="min-w-0 rounded-lg outline-none focus-visible:ring-2 focus-visible:ring-admin-accent"
                        >
                            <div className="flex items-start justify-between gap-3">
                                <div className="min-w-0">
                                    <h2 className="truncate font-semibold text-white">
                                        {customer.name}
                                    </h2>
                                    <p className="mt-1 text-sm text-admin-text-tertiary">
                                        {customer.phone ?? 'Sin teléfono'}
                                    </p>
                                </div>
                                <ChevronRight className="mt-0.5 size-5 shrink-0 text-admin-text-tertiary" />
                            </div>
                            <div className="mt-3 flex flex-wrap items-center gap-x-4 gap-y-1 text-xs text-admin-text-secondary">
                                <span>
                                    {formatCustomerCount(customer.orders_count)}
                                </span>
                                <span className="font-medium text-white tabular-nums">
                                    {customer.orders_count === 0
                                        ? 'Sin compras'
                                        : formatCustomerMoney.format(
                                              customer.historical_spend,
                                          )}
                                </span>
                                {customer.latest_order_date && (
                                    <span className="tabular-nums">
                                        Último{' '}
                                        {formatCustomerDate(
                                            customer.latest_order_date,
                                        )}
                                    </span>
                                )}
                            </div>
                        </Link>
                        <Button
                            type="button"
                            variant="ghost"
                            size="icon"
                            aria-label={`Editar cliente ${customer.name}`}
                            onClick={() => onEdit(customer)}
                            className="self-center text-admin-text-secondary"
                        >
                            <Pencil />
                        </Button>
                    </article>
                ))}
            </div>
        </>
    );
}
