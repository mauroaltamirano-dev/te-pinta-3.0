import { Link } from '@inertiajs/react';
import { ArrowUpRight } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { DashboardCard } from '@/features/dashboard/components/dashboard-card';
import {
    dashboardStatusLabels,
    formatDeliveryMoment,
} from '@/features/dashboard/presentation';
import type { DashboardOrder } from '@/features/dashboard/types';
import {
    deliveryTypeLabels,
    formatOrderMoney,
    orderStatusStyles,
    paymentStatusLabels,
} from '@/features/orders/presentation';

function OrderIdButton({
    id,
    onOpenOrder,
}: {
    id: string;
    onOpenOrder: (orderId: string) => void;
}) {
    return (
        <button
            type="button"
            onClick={() => onOpenOrder(id)}
            className="font-semibold text-admin-accent underline-offset-4 hover:underline focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-admin-accent"
        >
            {id}
        </button>
    );
}

export function TodayOrders({
    orders,
    onOpenOrder,
}: {
    orders: DashboardOrder[];
    onOpenOrder: (orderId: string) => void;
}) {
    return (
        <DashboardCard
            title="Pedidos de hoy"
            description="Resumen operativo del día, sin pedidos cancelados."
            data-testid="dashboard-today-orders"
            className="h-full"
            action={
                <Button
                    asChild
                    variant="ghost"
                    size="sm"
                    className="text-admin-accent hover:text-white"
                >
                    <Link href="/admin/orders">
                        Ver todos <ArrowUpRight aria-hidden="true" />
                    </Link>
                </Button>
            }
            contentClassName="min-w-0"
        >
            {orders.length === 0 ? (
                <p className="rounded-lg bg-admin-surface-raised px-4 py-8 text-center text-sm text-admin-text-secondary">
                    No hay pedidos para hoy.
                </p>
            ) : (
                <>
                    <div className="hidden overflow-hidden rounded-lg border border-admin-border md:block">
                        <table className="w-full table-fixed text-left text-sm">
                            <thead className="bg-admin-surface-raised text-xs text-admin-text-tertiary uppercase">
                                <tr>
                                    <th className="w-[17%] px-3 py-3 font-medium">
                                        Pedido
                                    </th>
                                    <th className="w-[22%] px-3 py-3 font-medium">
                                        Cliente
                                    </th>
                                    <th className="w-[18%] px-3 py-3 font-medium">
                                        Entrega
                                    </th>
                                    <th className="w-[17%] px-3 py-3 font-medium">
                                        Estado
                                    </th>
                                    <th className="w-[14%] px-3 py-3 font-medium">
                                        Pago
                                    </th>
                                    <th className="w-[12%] px-3 py-3 text-right font-medium">
                                        Total
                                    </th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-admin-border">
                                {orders.map((order) => (
                                    <tr key={order.id}>
                                        <td className="px-3 py-3">
                                            <OrderIdButton
                                                id={order.id}
                                                onOpenOrder={onOpenOrder}
                                            />
                                        </td>
                                        <td className="truncate px-3 py-3 font-medium text-white">
                                            {order.customer_name}
                                        </td>
                                        <td className="px-3 py-3 text-admin-text-secondary">
                                            {formatDeliveryMoment(
                                                order.delivery_time,
                                                order.delivery_period,
                                            )}
                                            <span className="block text-xs text-admin-text-tertiary">
                                                {
                                                    deliveryTypeLabels[
                                                        order.delivery_type
                                                    ]
                                                }
                                            </span>
                                        </td>
                                        <td className="px-3 py-3">
                                            <span
                                                className={`inline-flex rounded-full px-2.5 py-1 text-xs font-semibold ${orderStatusStyles[order.status]}`}
                                            >
                                                {
                                                    dashboardStatusLabels[
                                                        order.status
                                                    ]
                                                }
                                            </span>
                                        </td>
                                        <td className="px-3 py-3 text-admin-text-secondary">
                                            {
                                                paymentStatusLabels[
                                                    order.payment_status
                                                ]
                                            }
                                        </td>
                                        <td className="px-3 py-3 text-right font-semibold text-white tabular-nums">
                                            {formatOrderMoney.format(
                                                order.total,
                                            )}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>

                    <ol className="grid gap-2 md:hidden">
                        {orders.map((order) => (
                            <li
                                key={order.id}
                                className="rounded-lg border border-admin-border bg-admin-surface-raised p-3"
                            >
                                <div className="flex items-start justify-between gap-3">
                                    <div className="min-w-0">
                                        <OrderIdButton
                                            id={order.id}
                                            onOpenOrder={onOpenOrder}
                                        />
                                        <p className="mt-1 truncate text-sm font-medium text-white">
                                            {order.customer_name}
                                        </p>
                                    </div>
                                    <span
                                        className={`shrink-0 rounded-full px-2 py-1 text-xs font-semibold ${orderStatusStyles[order.status]}`}
                                    >
                                        {dashboardStatusLabels[order.status]}
                                    </span>
                                </div>
                                <div className="mt-3 grid grid-cols-2 gap-3 border-t border-admin-border pt-3 text-xs text-admin-text-secondary">
                                    <p>
                                        {formatDeliveryMoment(
                                            order.delivery_time,
                                            order.delivery_period,
                                        )}{' '}
                                        ·{' '}
                                        {
                                            deliveryTypeLabels[
                                                order.delivery_type
                                            ]
                                        }
                                    </p>
                                    <p className="text-right">
                                        {
                                            paymentStatusLabels[
                                                order.payment_status
                                            ]
                                        }
                                    </p>
                                    <p className="col-span-2 text-right text-base font-semibold text-white tabular-nums">
                                        {formatOrderMoney.format(order.total)}
                                    </p>
                                </div>
                            </li>
                        ))}
                    </ol>
                </>
            )}
        </DashboardCard>
    );
}
