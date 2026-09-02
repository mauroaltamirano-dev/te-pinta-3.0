import { Head, Link, router } from '@inertiajs/react';
import { ArrowLeft, ChevronLeft, ChevronRight, RotateCcw } from 'lucide-react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
import {
    formatCustomerCount,
    formatCustomerDate,
} from '@/features/customers/presentation';

interface DeletedCustomer {
    id: number;
    name: string;
    orders_count: number;
    deleted_at: string;
}

interface DeletedCustomersPage {
    data: DeletedCustomer[];
    current_page: number;
    last_page: number;
    prev_page_url: string | null;
    next_page_url: string | null;
    total: number;
}

export default function CustomerTrash({
    customers,
}: {
    customers: DeletedCustomersPage;
}) {
    const [restoreError, setRestoreError] = useState<string | null>(null);
    const restore = (customer: DeletedCustomer) => {
        setRestoreError(null);
        router.patch(
            `/admin/customers/trash/${customer.id}/restore`,
            {},
            {
                onError: (errors) => {
                    if (typeof errors.restore === 'string') {
                        setRestoreError(errors.restore);
                    }
                },
            },
        );
    };

    return (
        <>
            <Head title="Papelera de clientes" />
            <section className="mx-auto grid max-w-5xl gap-6 font-orders">
                <header>
                    <Link
                        href="/admin/customers"
                        className="mb-3 inline-flex items-center gap-2 text-sm text-admin-text-tertiary hover:text-white"
                    >
                        <ArrowLeft className="size-4" /> Volver a clientes
                    </Link>
                    <p className="text-sm font-medium text-admin-accent">
                        Registros eliminados
                    </p>
                    <h1 className="mt-1 text-3xl font-semibold tracking-tight text-white">
                        Papelera de clientes
                    </h1>
                    <p className="mt-1 text-sm text-admin-text-tertiary">
                        Restaurar un cliente conserva sus pedidos y todos sus
                        datos históricos.
                    </p>
                </header>

                {restoreError && (
                    <p
                        role="alert"
                        className="rounded-lg border border-destructive/40 bg-destructive/10 px-4 py-3 text-sm text-destructive"
                    >
                        {restoreError}
                    </p>
                )}

                <div className="overflow-hidden rounded-lg bg-admin-surface">
                    <div className="border-b border-admin-border px-4 py-4 sm:px-5">
                        <h2 className="font-semibold text-white">
                            {customers.total}{' '}
                            {customers.total === 1
                                ? 'cliente eliminado'
                                : 'clientes eliminados'}
                        </h2>
                    </div>

                    {customers.data.length === 0 ? (
                        <p
                            role="status"
                            className="px-5 py-14 text-center text-sm text-admin-text-tertiary"
                        >
                            La papelera está vacía.
                        </p>
                    ) : (
                        <>
                            <div className="hidden overflow-x-auto md:block">
                                <table className="w-full text-left text-sm">
                                    <thead className="border-b border-admin-border text-admin-text-tertiary">
                                        <tr>
                                            <th
                                                className="px-4 py-3 font-medium"
                                                scope="col"
                                            >
                                                Nombre
                                            </th>
                                            <th
                                                className="px-4 py-3 font-medium"
                                                scope="col"
                                            >
                                                Eliminado
                                            </th>
                                            <th
                                                className="px-4 py-3 font-medium"
                                                scope="col"
                                            >
                                                Pedidos asociados
                                            </th>
                                            <th
                                                className="px-4 py-3 text-right font-medium"
                                                scope="col"
                                            >
                                                Acción
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y divide-admin-border">
                                        {customers.data.map((customer) => (
                                            <tr key={customer.id}>
                                                <td className="px-4 py-4 font-medium text-white">
                                                    {customer.name}
                                                </td>
                                                <td className="px-4 py-4 text-admin-text-secondary tabular-nums">
                                                    {formatCustomerDate(
                                                        customer.deleted_at,
                                                    )}
                                                </td>
                                                <td className="px-4 py-4 text-admin-text-secondary">
                                                    {formatCustomerCount(
                                                        customer.orders_count,
                                                    )}
                                                </td>
                                                <td className="px-4 py-3 text-right">
                                                    <Button
                                                        type="button"
                                                        size="sm"
                                                        variant="outline"
                                                        onClick={() =>
                                                            restore(customer)
                                                        }
                                                        aria-label={`Restaurar ${customer.name}`}
                                                    >
                                                        <RotateCcw /> Restaurar
                                                    </Button>
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>

                            <div className="divide-y divide-admin-border md:hidden">
                                {customers.data.map((customer) => (
                                    <article
                                        key={customer.id}
                                        className="px-4 py-4"
                                    >
                                        <div className="flex items-start justify-between gap-3">
                                            <div className="min-w-0">
                                                <h2 className="truncate font-semibold text-white">
                                                    {customer.name}
                                                </h2>
                                                <p className="mt-1 text-xs text-admin-text-tertiary">
                                                    Eliminado el{' '}
                                                    {formatCustomerDate(
                                                        customer.deleted_at,
                                                    )}
                                                </p>
                                                <p className="mt-2 text-sm text-admin-text-secondary">
                                                    {formatCustomerCount(
                                                        customer.orders_count,
                                                    )}{' '}
                                                    asociados
                                                </p>
                                            </div>
                                            <Button
                                                type="button"
                                                size="icon"
                                                variant="outline"
                                                onClick={() =>
                                                    restore(customer)
                                                }
                                                aria-label={`Restaurar ${customer.name}`}
                                            >
                                                <RotateCcw />
                                            </Button>
                                        </div>
                                    </article>
                                ))}
                            </div>
                        </>
                    )}

                    <nav
                        aria-label="Paginación de la papelera de clientes"
                        className="flex items-center justify-between border-t border-admin-border px-4 py-4 sm:px-5"
                    >
                        <p className="text-sm text-admin-text-tertiary">
                            Página {customers.current_page} de{' '}
                            {customers.last_page}
                        </p>
                        <div className="flex gap-2">
                            <Button
                                variant="outline"
                                size="icon"
                                disabled={!customers.prev_page_url}
                                asChild={Boolean(customers.prev_page_url)}
                                aria-label="Página anterior"
                            >
                                {customers.prev_page_url ? (
                                    <Link href={customers.prev_page_url}>
                                        <ChevronLeft />
                                    </Link>
                                ) : (
                                    <span>
                                        <ChevronLeft />
                                    </span>
                                )}
                            </Button>
                            <Button
                                variant="outline"
                                size="icon"
                                disabled={!customers.next_page_url}
                                asChild={Boolean(customers.next_page_url)}
                                aria-label="Página siguiente"
                            >
                                {customers.next_page_url ? (
                                    <Link href={customers.next_page_url}>
                                        <ChevronRight />
                                    </Link>
                                ) : (
                                    <span>
                                        <ChevronRight />
                                    </span>
                                )}
                            </Button>
                        </div>
                    </nav>
                </div>
            </section>
        </>
    );
}
