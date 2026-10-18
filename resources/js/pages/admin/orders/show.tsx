import { Head, Link, router } from '@inertiajs/react';
import { ArrowLeft, Trash2 } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { OrderDetailContent } from '@/features/orders/components/order-detail-content';
import type { AdminOrder } from '@/features/orders/types';

export default function OrderDetail({
    order,
    summary,
}: {
    order: AdminOrder;
    summary: string;
}) {
    const remove = () => {
        if (
            window.confirm(
                `¿Querés eliminar ${order.id}? El registro se conservará en el historial.`,
            )
        ) {
            router.delete(`/admin/orders/${order.id}`);
        }
    };

    return (
        <>
            <Head title={`Pedido ${order.id}`} />
            <section className="mx-auto grid max-w-5xl gap-6 font-orders">
                <header className="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                    <div>
                        <Link
                            href="/admin/orders"
                            className="mb-3 inline-flex items-center gap-2 text-sm text-admin-text-tertiary hover:text-admin-text-primary"
                        >
                            <ArrowLeft className="size-4" /> Volver a pedidos
                        </Link>
                        <h1 className="mt-1 text-3xl font-semibold tracking-tight text-admin-text-primary">
                            Detalle del pedido
                        </h1>
                        <p className="mt-1 text-sm text-admin-text-tertiary">
                            Los importes corresponden a los valores guardados al
                            crear el pedido.
                        </p>
                    </div>
                    <Button
                        type="button"
                        variant="outline"
                        onClick={remove}
                        className="text-destructive hover:text-destructive"
                    >
                        <Trash2 /> Eliminar pedido
                    </Button>
                </header>

                <OrderDetailContent order={order} summary={summary} />
            </section>
        </>
    );
}
