import { Link } from '@inertiajs/react';
import { ReceiptText } from 'lucide-react';
import { Button } from '@/components/ui/button';
import {
    formatDeliveryPeriod,
    formatOrderCustomer,
    deliveryTypeLabels,
    formatOrderDate,
    formatOrderMoney,
    orderConditionLabels,
    pricingModeLabels,
} from '@/features/orders/presentation';
import type { AdminOrder, OrdersView } from '@/features/orders/types';
import { cn } from '@/lib/utils';
import { OrderPaymentToggle, OrderStatusSelect } from './order-quick-controls';

interface OrderCardProps {
    order: AdminOrder;
    view?: OrdersView;
    onOpen: (order: AdminOrder) => void;
}

function unitsLabel(units: number): string {
    return `${units} ${units === 1 ? 'unidad' : 'unidades'}`;
}

export function OrderCard({ order, view = 'grid', onOpen }: OrderCardProps) {
    const deliveryMoment = order.delivery_time
        ? order.delivery_time.slice(0, 5)
        : formatDeliveryPeriod(order.delivery_period);

    return (
        <article
            data-testid="order-card"
            aria-label={`Pedido ${order.id} de ${formatOrderCustomer(order)}`}
            className={cn(
                'min-w-0 overflow-hidden rounded-lg border border-admin-border bg-admin-surface',
                view === 'grid' && 'flex h-full flex-col',
                view === 'list' &&
                    'grid md:grid-cols-[minmax(13rem,0.8fr)_minmax(18rem,1.35fr)_minmax(14rem,0.8fr)]',
            )}
        >
            <header
                className={cn(
                    'grid border-b border-admin-border',
                    view === 'grid' && 'gap-3 p-4',
                    view === 'list' &&
                        'gap-2 p-3 md:border-r md:border-b-0 md:border-admin-border md:p-4',
                )}
            >
                <div className="flex flex-wrap items-start justify-between gap-3">
                    <p className="pt-1 text-xs text-admin-text-tertiary">
                        {formatOrderDate(order.delivery_date)} ·{' '}
                        {deliveryMoment}
                    </p>
                    <OrderStatusSelect order={order} />
                </div>

                <div className="flex min-w-0 items-start justify-between gap-3">
                    <div className="min-w-0">
                        {order.customer !== null ? (
                            <Link
                                href={`/admin/customers/${order.customer.id}`}
                                className={cn(
                                    'block font-semibold text-white underline-offset-4 hover:underline focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-admin-accent',
                                    view === 'grid' && 'truncate text-lg',
                                    view === 'list' && 'text-base leading-snug',
                                )}
                            >
                                {formatOrderCustomer(order)}
                            </Link>
                        ) : (
                            <p className="font-semibold text-white">
                                {formatOrderCustomer(order)}
                            </p>
                        )}
                        <button
                            type="button"
                            onClick={() => onOpen(order)}
                            className="mt-1 text-sm font-medium text-admin-accent underline-offset-4 hover:underline focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-admin-accent"
                        >
                            {order.id}
                        </button>
                    </div>
                    <div className="shrink-0 text-right text-xs text-admin-text-secondary">
                        <p>{deliveryTypeLabels[order.delivery_type]}</p>
                        <p className="mt-1">
                            {orderConditionLabels[order.condition]}
                        </p>
                    </div>
                </div>
            </header>

            <section
                data-testid="order-card-items"
                aria-label={`Variedades de ${order.id}`}
                className={cn(
                    'grid flex-1 content-start',
                    view === 'grid' && 'gap-3 p-4',
                    view === 'list' &&
                        'gap-2 p-3 md:border-r md:border-admin-border md:p-4',
                )}
            >
                <h3 className="text-sm font-semibold text-white">Pedido</h3>
                {order.items?.length ? (
                    <ul className="grid gap-3">
                        {order.items.map((item, index) => (
                            <li
                                key={`${order.id}-item-${index}`}
                                className="grid gap-2"
                            >
                                <div className="flex items-start justify-between gap-4">
                                    <div className="min-w-0">
                                        <p className="text-sm font-medium text-[#e0e6e9]">
                                            {item.quantity} ×{' '}
                                            {item.pricing_mode ===
                                            'combined_dozen'
                                                ? 'Docena combinada'
                                                : (item.product_name ??
                                                  'Producto')}
                                        </p>
                                        <p className="mt-1 text-xs text-admin-text-tertiary">
                                            {item.pricing_mode !==
                                                'combined_dozen' && (
                                                <>
                                                    {pricingModeLabels[
                                                        item.pricing_mode
                                                    ] ?? item.pricing_mode}{' '}
                                                    ·{' '}
                                                </>
                                            )}
                                            {unitsLabel(item.units_count)}
                                        </p>
                                    </div>
                                    <span className="shrink-0 text-sm text-admin-text-secondary tabular-nums">
                                        {formatOrderMoney.format(item.subtotal)}
                                    </span>
                                </div>

                                {item.composition.length > 0 && (
                                    <div className="grid gap-1.5 border-l border-admin-accent/50 pl-3">
                                        <p className="text-[0.6875rem] font-medium tracking-wide text-admin-text-tertiary uppercase">
                                            Variedades
                                        </p>
                                        <ul
                                            data-testid="order-card-composition"
                                            className="grid gap-1.5"
                                        >
                                            {item.composition.map(
                                                (part, partIndex) => (
                                                    <li
                                                        key={`${order.id}-item-${index}-part-${partIndex}`}
                                                        className="text-xs text-admin-text-secondary"
                                                    >
                                                        {part.quantity} ×{' '}
                                                        {part.product_name}
                                                    </li>
                                                ),
                                            )}
                                        </ul>
                                    </div>
                                )}
                            </li>
                        ))}
                    </ul>
                ) : (
                    <p className="text-sm text-admin-text-tertiary">
                        Sin variedades registradas.
                    </p>
                )}

                {Boolean(order.toppings?.length) && (
                    <div
                        data-testid="order-card-toppings"
                        className="grid gap-2 border-t border-admin-border pt-4"
                    >
                        <h4 className="text-xs font-semibold tracking-wide text-admin-text-tertiary uppercase">
                            Adicionales
                        </h4>
                        <ul className="grid gap-2">
                            {order.toppings?.map((topping, index) => (
                                <li
                                    key={`${order.id}-topping-${index}`}
                                    className="flex items-start justify-between gap-4 text-sm text-admin-text-secondary"
                                >
                                    <span>
                                        {topping.quantity} × {topping.name}
                                    </span>
                                    <span className="shrink-0 tabular-nums">
                                        {formatOrderMoney.format(
                                            topping.subtotal,
                                        )}
                                    </span>
                                </li>
                            ))}
                        </ul>
                    </div>
                )}
            </section>

            <footer
                className={cn(
                    'grid content-between border-t border-admin-border',
                    view === 'grid' && 'gap-3 p-4',
                    view === 'list' && 'gap-2 p-3 md:border-t-0 md:p-4',
                )}
            >
                <div className="flex items-baseline justify-between gap-4">
                    <span className="font-semibold text-white">Total</span>
                    <span className="text-xl font-semibold text-admin-accent tabular-nums">
                        {formatOrderMoney.format(order.total)}
                    </span>
                </div>
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <OrderPaymentToggle order={order} />
                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        onClick={() => onOpen(order)}
                    >
                        <ReceiptText /> Ver comprobante
                    </Button>
                </div>
            </footer>
        </article>
    );
}
