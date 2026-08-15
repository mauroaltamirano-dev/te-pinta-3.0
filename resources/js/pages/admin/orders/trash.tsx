import { Head, Link, router } from '@inertiajs/react';
import { ArrowLeft, RotateCcw } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import {
    formatOrderMoney,
    orderStatusLabels,
} from '@/features/orders/presentation';
import type { OrderStatus } from '@/features/orders/types';

interface DeletedOrder {
    id: string;
    customer_name: string;
    status: OrderStatus;
    total: number;
    delivery_date: string;
    deleted_at: string;
}

interface DeletedOrdersPage {
    data: DeletedOrder[];
    prev_page_url: string | null;
    next_page_url: string | null;
    total: number;
}

export default function OrderTrash({ orders }: { orders: DeletedOrdersPage }) {
    const restore = (order: DeletedOrder) => {
        router.patch(`/admin/orders/trash/${order.id}/restore`);
    };

    return (
        <>
            <Head title="Papelera de pedidos" />
            <section className="mx-auto grid max-w-5xl gap-6 font-orders">
                <header>
                    <Link
                        href="/admin/orders"
                        className="mb-3 inline-flex items-center gap-2 text-sm text-admin-text-tertiary hover:text-white"
                    >
                        <ArrowLeft className="size-4" /> Volver a pedidos
                    </Link>
                    <p className="text-sm font-medium text-admin-accent">
                        Registros eliminados
                    </p>
                    <h1 className="mt-1 text-3xl font-semibold tracking-tight text-white">
                        Papelera de pedidos
                    </h1>
                    <p className="mt-1 text-sm text-admin-text-tertiary">
                        Restaurar conserva el ID, los importes y todos los
                        snapshots del pedido.
                    </p>
                </header>

                <Card>
                    <CardHeader>
                        <CardTitle>{orders.total} pedidos eliminados</CardTitle>
                    </CardHeader>
                    <CardContent>
                        {orders.data.length === 0 ? (
                            <p
                                role="status"
                                className="py-12 text-center text-sm text-muted-foreground"
                            >
                                No hay pedidos en la papelera.
                            </p>
                        ) : (
                            <div className="overflow-x-auto">
                                <table className="w-full min-w-[680px] text-left text-sm">
                                    <thead className="border-b text-muted-foreground">
                                        <tr>
                                            <th
                                                scope="col"
                                                className="px-3 py-3 font-medium"
                                            >
                                                Pedido
                                            </th>
                                            <th
                                                scope="col"
                                                className="px-3 py-3 font-medium"
                                            >
                                                Cliente
                                            </th>
                                            <th
                                                scope="col"
                                                className="px-3 py-3 font-medium"
                                            >
                                                Estado
                                            </th>
                                            <th
                                                scope="col"
                                                className="px-3 py-3 font-medium"
                                            >
                                                Total
                                            </th>
                                            <th
                                                scope="col"
                                                className="px-3 py-3 font-medium"
                                            >
                                                Acción
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y">
                                        {orders.data.map((order) => (
                                            <tr key={order.id}>
                                                <td className="px-3 py-4 font-medium">
                                                    {order.id}
                                                </td>
                                                <td className="px-3 py-4">
                                                    {order.customer_name}
                                                </td>
                                                <td className="px-3 py-4">
                                                    {
                                                        orderStatusLabels[
                                                            order.status
                                                        ]
                                                    }
                                                </td>
                                                <td className="px-3 py-4 tabular-nums">
                                                    {formatOrderMoney.format(
                                                        order.total,
                                                    )}
                                                </td>
                                                <td className="px-3 py-4">
                                                    <Button
                                                        type="button"
                                                        size="sm"
                                                        variant="outline"
                                                        onClick={() =>
                                                            restore(order)
                                                        }
                                                    >
                                                        <RotateCcw /> Restaurar{' '}
                                                        {order.id}
                                                    </Button>
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        )}
                        <Pagination
                            label={`${orders.total} pedidos`}
                            previous={orders.prev_page_url}
                            next={orders.next_page_url}
                        />
                    </CardContent>
                </Card>
            </section>
        </>
    );
}

function Pagination({
    label,
    previous,
    next,
}: {
    label: string;
    previous: string | null;
    next: string | null;
}) {
    return (
        <nav
            aria-label="Páginas de la papelera de pedidos"
            className="mt-4 flex items-center justify-between border-t pt-4"
        >
            <p className="text-xs text-muted-foreground">{label}</p>
            <div className="flex gap-2">
                <Button
                    variant="outline"
                    size="sm"
                    disabled={!previous}
                    asChild={Boolean(previous)}
                >
                    {previous ? (
                        <Link href={previous}>Anterior</Link>
                    ) : (
                        <span>Anterior</span>
                    )}
                </Button>
                <Button
                    variant="outline"
                    size="sm"
                    disabled={!next}
                    asChild={Boolean(next)}
                >
                    {next ? (
                        <Link href={next}>Siguiente</Link>
                    ) : (
                        <span>Siguiente</span>
                    )}
                </Button>
            </div>
        </nav>
    );
}
