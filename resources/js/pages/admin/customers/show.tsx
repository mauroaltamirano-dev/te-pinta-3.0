import { Head, Link, router } from '@inertiajs/react';
import { ArrowLeft, Pencil, StickyNote, Trash2 } from 'lucide-react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
import { CustomerFormDialog } from '@/features/customers/components/customer-form-dialog';
import { CustomerOrderHistory } from '@/features/customers/components/customer-order-history';
import { CustomerSummary } from '@/features/customers/components/customer-summary';
import type { Customer } from '@/features/customers/types';
import { OrderDetailSheet } from '@/features/orders/components/order-detail-sheet';
import type { AdminOrder } from '@/features/orders/types';

interface CustomerDetailProps {
    customer: Customer;
    selectedOrder?: AdminOrder | null;
    selectedOrderSummary?: string | null;
}

export default function CustomerDetail({
    customer,
    selectedOrder = null,
    selectedOrderSummary = null,
}: CustomerDetailProps) {
    const [editing, setEditing] = useState(false);
    const remove = () => {
        if (
            window.confirm(
                `¿Enviar a ${customer.name} a la papelera? Sus pedidos históricos se conservarán.`,
            )
        ) {
            router.delete(`/admin/customers/${customer.id}`);
        }
    };
    const openOrder = (order: string) =>
        router.get(
            `/admin/customers/${customer.id}`,
            { order },
            {
                preserveState: true,
                preserveScroll: true,
                only: ['selectedOrder', 'selectedOrderSummary'],
            },
        );
    const closeOrder = () =>
        router.get(
            `/admin/customers/${customer.id}`,
            {},
            {
                preserveState: true,
                preserveScroll: true,
                only: ['selectedOrder', 'selectedOrderSummary'],
            },
        );

    return (
        <>
            <Head title={customer.name} />
            <section className="grid gap-6 font-orders">
                <header className="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                    <div className="min-w-0">
                        <Link
                            href="/admin/customers"
                            className="mb-3 inline-flex items-center gap-2 text-sm text-admin-text-tertiary hover:text-admin-text-primary"
                        >
                            <ArrowLeft className="size-4" /> Clientes
                        </Link>
                        <h1 className="truncate text-3xl font-semibold tracking-tight text-admin-text-primary">
                            {customer.name}
                        </h1>
                        <p className="mt-1 text-sm text-admin-text-tertiary">
                            Ficha operativa, contacto e historial de pedidos.
                        </p>
                    </div>
                    <div className="flex flex-wrap gap-2">
                        <Button
                            type="button"
                            onClick={() => setEditing(true)}
                            className="h-11 rounded-lg px-4"
                        >
                            <Pencil /> Editar
                        </Button>
                        <Button
                            type="button"
                            variant="outline"
                            onClick={remove}
                            className="h-11 rounded-lg px-4 text-destructive hover:text-destructive"
                        >
                            <Trash2 /> Enviar a papelera
                        </Button>
                    </div>
                </header>

                <CustomerSummary customer={customer} />

                <div className="grid min-w-0 gap-6 xl:grid-cols-[minmax(0,1fr)_20rem]">
                    <section className="min-w-0 overflow-hidden rounded-lg bg-admin-surface">
                        <header className="border-b border-admin-border px-4 py-4 sm:px-5">
                            <h2 className="text-lg font-semibold text-admin-text-primary">
                                Historial de pedidos
                            </h2>
                            <p className="mt-1 text-sm text-admin-text-tertiary">
                                Más recientes primero. Abrí un pedido sin salir
                                de esta ficha.
                            </p>
                        </header>
                        <CustomerOrderHistory
                            orders={customer.orders ?? []}
                            onOpenOrder={openOrder}
                        />
                    </section>

                    <aside className="self-start rounded-lg bg-admin-surface p-5">
                        <h2 className="flex items-center gap-2 text-base font-semibold text-admin-text-primary">
                            <StickyNote className="size-4 text-admin-accent-text" />{' '}
                            Notas privadas
                        </h2>
                        <p className="mt-4 text-sm leading-6 whitespace-pre-wrap text-admin-text-secondary">
                            {customer.notes ?? 'Sin notas privadas.'}
                        </p>
                        <p className="mt-5 border-t border-admin-border pt-4 text-xs text-admin-text-tertiary">
                            Solo visibles para administradores.
                        </p>
                    </aside>
                </div>
            </section>
            {editing && (
                <CustomerFormDialog
                    open={editing}
                    onOpenChange={setEditing}
                    customer={customer}
                />
            )}
            <OrderDetailSheet
                order={selectedOrder}
                summary={selectedOrderSummary}
                onClose={closeOrder}
            />
        </>
    );
}
