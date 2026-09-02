import { Badge } from '@/components/ui/badge';
import {
    formatCustomerDate,
    formatCustomerMoney,
} from '@/features/customers/presentation';
import type { CustomerOrderSummary } from '@/features/customers/types';
import {
    deliveryTypeLabels,
    orderStatusLabels,
    orderStatusStyles,
    paymentMethodLabels,
    paymentStatusLabels,
} from '@/features/orders/presentation';

function PaymentLabel({ order }: { order: CustomerOrderSummary }) {
    return (
        <span>
            {paymentStatusLabels[order.payment_status]}
            {order.payment_method
                ? ` · ${paymentMethodLabels[order.payment_method]}`
                : ''}
        </span>
    );
}

export function CustomerOrderHistory({
    orders,
    onOpenOrder,
}: {
    orders: CustomerOrderSummary[];
    onOpenOrder: (orderId: string) => void;
}) {
    if (orders.length === 0) {
        return (
            <p
                role="status"
                className="px-5 py-12 text-center text-sm text-admin-text-tertiary"
            >
                Este cliente todavía no tiene pedidos.
            </p>
        );
    }

    return (
        <>
            <div className="hidden overflow-x-auto md:block">
                <table className="w-full text-left text-sm">
                    <thead className="border-b border-admin-border text-admin-text-tertiary">
                        <tr>
                            <th className="px-4 py-3 font-medium" scope="col">
                                Pedido
                            </th>
                            <th className="px-4 py-3 font-medium" scope="col">
                                Fecha
                            </th>
                            <th className="px-4 py-3 font-medium" scope="col">
                                Entrega
                            </th>
                            <th className="px-4 py-3 font-medium" scope="col">
                                Estado
                            </th>
                            <th className="px-4 py-3 font-medium" scope="col">
                                Pago
                            </th>
                            <th
                                className="px-4 py-3 text-right font-medium"
                                scope="col"
                            >
                                Total
                            </th>
                        </tr>
                    </thead>
                    <tbody className="divide-y divide-admin-border">
                        {orders.map((order) => (
                            <tr key={order.id}>
                                <td className="px-4 py-4">
                                    <button
                                        type="button"
                                        className="font-semibold text-admin-accent underline-offset-4 hover:underline"
                                        onClick={() => onOpenOrder(order.id)}
                                    >
                                        {order.id}
                                    </button>
                                </td>
                                <td className="px-4 py-4 text-admin-text-secondary tabular-nums">
                                    {formatCustomerDate(order.date)}
                                </td>
                                <td className="px-4 py-4 text-admin-text-secondary">
                                    {deliveryTypeLabels[order.delivery_type]}
                                </td>
                                <td className="px-4 py-4">
                                    <Badge
                                        className={`border-0 shadow-none ${orderStatusStyles[order.status]}`}
                                    >
                                        {orderStatusLabels[order.status]}
                                    </Badge>
                                </td>
                                <td className="px-4 py-4 text-admin-text-secondary">
                                    <PaymentLabel order={order} />
                                </td>
                                <td className="px-4 py-4 text-right font-semibold text-white tabular-nums">
                                    {formatCustomerMoney.format(order.total)}
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>

            <div className="divide-y divide-admin-border md:hidden">
                {orders.map((order) => (
                    <article key={order.id} className="px-4 py-4">
                        <div className="flex items-start justify-between gap-4">
                            <div>
                                <button
                                    type="button"
                                    className="font-semibold text-admin-accent underline-offset-4 hover:underline"
                                    onClick={() => onOpenOrder(order.id)}
                                >
                                    {order.id}
                                </button>
                                <p className="mt-1 text-xs text-admin-text-tertiary tabular-nums">
                                    {formatCustomerDate(order.date)} ·{' '}
                                    {deliveryTypeLabels[order.delivery_type]}
                                </p>
                            </div>
                            <p className="font-semibold text-white tabular-nums">
                                {formatCustomerMoney.format(order.total)}
                            </p>
                        </div>
                        <div className="mt-3 flex flex-wrap items-center gap-2 text-xs text-admin-text-secondary">
                            <Badge
                                className={`border-0 shadow-none ${orderStatusStyles[order.status]}`}
                            >
                                {orderStatusLabels[order.status]}
                            </Badge>
                            <PaymentLabel order={order} />
                        </div>
                    </article>
                ))}
            </div>
        </>
    );
}
