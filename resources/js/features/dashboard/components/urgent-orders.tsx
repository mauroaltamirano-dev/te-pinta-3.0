import { AlertTriangle } from 'lucide-react';
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
} from '@/features/orders/presentation';

export function UrgentOrders({
    orders,
    onOpenOrder,
}: {
    orders: DashboardOrder[];
    onOpenOrder: (orderId: string) => void;
}) {
    return (
        <DashboardCard
            title="Pedidos urgentes de hoy"
            description="Por confirmar, en espera y preparados, en prioridad operativa."
            data-testid="dashboard-urgent-orders"
            className="h-full"
            action={
                <span className="inline-flex items-center gap-1.5 rounded-full bg-admin-selection px-2.5 py-1 text-xs font-semibold text-admin-accent">
                    <AlertTriangle className="size-3.5" aria-hidden="true" />
                    Hoy
                </span>
            }
        >
            {orders.length === 0 ? (
                <p className="rounded-lg bg-admin-surface-raised px-4 py-8 text-center text-sm text-admin-text-secondary">
                    No hay pedidos pendientes para hoy.
                </p>
            ) : (
                <ol className="grid gap-2">
                    {orders.map((order) => (
                        <li
                            key={order.id}
                            className="grid gap-3 rounded-lg border border-admin-border bg-admin-surface-raised p-3 sm:grid-cols-[minmax(0,1fr)_auto] sm:items-center sm:p-4"
                        >
                            <div className="flex min-w-0 items-start gap-3">
                                <span
                                    className={`mt-0.5 shrink-0 rounded-full px-2.5 py-1 text-xs font-semibold ${orderStatusStyles[order.status]}`}
                                >
                                    {dashboardStatusLabels[order.status]}
                                </span>
                                <div className="min-w-0">
                                    <button
                                        type="button"
                                        onClick={() => onOpenOrder(order.id)}
                                        className="font-semibold text-admin-accent underline-offset-4 hover:underline focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-admin-accent"
                                    >
                                        {order.id}
                                    </button>
                                    <p className="mt-1 truncate text-sm font-medium text-white">
                                        {order.customer_name}
                                    </p>
                                    <p className="mt-1 text-xs text-admin-text-tertiary">
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
                                </div>
                            </div>
                            <p className="text-right text-base font-semibold text-white tabular-nums">
                                {formatOrderMoney.format(order.total)}
                            </p>
                        </li>
                    ))}
                </ol>
            )}
        </DashboardCard>
    );
}
