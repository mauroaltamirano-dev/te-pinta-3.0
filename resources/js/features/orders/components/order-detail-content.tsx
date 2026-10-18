import { Link, router } from '@inertiajs/react';
import { ExternalLink, ReceiptText } from 'lucide-react';
import { useState } from 'react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { CopyOrderDetailButton } from '@/features/orders/components/copy-order-detail-button';
import { OrderEditButton } from '@/features/orders/components/order-edit-button';
import {
    formatDeliveryPeriod,
    formatOrderCustomer,
    deliveryTypeLabels,
    formatOrderDate,
    formatOrderMoney,
    orderConditionLabels,
    orderStatusLabels,
    orderStatuses,
    paymentMethodLabels,
    paymentStatusLabels,
    pricingModeLabels,
} from '@/features/orders/presentation';
import type {
    AdminOrder,
    OrderStatus,
    PaymentMethod,
    PaymentStatus,
} from '@/features/orders/types';

interface OrderDetailContentProps {
    order: AdminOrder;
    summary: string;
    compact?: boolean;
    showFullDetailLink?: boolean;
}

function DetailSection({
    title,
    children,
}: {
    title: string;
    children: React.ReactNode;
}) {
    return (
        <section className="rounded-lg bg-admin-surface-raised p-4 text-admin-text-secondary sm:p-5">
            <h2 className="mb-4 text-sm font-semibold text-admin-text-primary">
                {title}
            </h2>
            {children}
        </section>
    );
}

export function OrderDetailContent({
    order,
    summary,
    compact = false,
    showFullDetailLink = false,
}: OrderDetailContentProps) {
    const [paymentSelection, setPaymentSelection] = useState<PaymentStatus>(
        order.payment_status,
    );
    const [paymentMethodSelection, setPaymentMethodSelection] = useState<
        PaymentMethod | ''
    >(order.payment_method ?? '');
    const closed = order.status === 'delivered' || order.status === 'cancelled';
    const changeStatus = (status: OrderStatus) =>
        router.patch(
            `/admin/orders/${order.id}/status`,
            { status },
            { preserveScroll: true },
        );
    const changePayment = (
        payment_status: PaymentStatus,
        payment_method: PaymentMethod | null,
    ) =>
        router.patch(
            `/admin/orders/${order.id}/payment-status`,
            { payment_status, payment_method },
            { preserveScroll: true },
        );
    const hasRawPricing = order.items_cooked_subtotal != null;

    return (
        <div className="grid gap-4 font-orders">
            <section
                data-testid="order-ticket-header"
                className="border-y border-dashed border-admin-border bg-admin-surface px-4 py-5 sm:px-5"
            >
                <div className="flex items-start justify-between gap-5">
                    <div className="min-w-0">
                        <p className="flex items-center gap-2 text-xs font-medium tracking-[0.14em] text-admin-accent-text uppercase">
                            <ReceiptText className="size-4" /> Orden de
                            producción
                        </p>
                        <h2 className="mt-2 text-2xl font-semibold tracking-tight text-admin-text-primary sm:text-3xl">
                            {order.id}
                        </h2>
                    </div>
                    <div className="shrink-0 text-right">
                        <p className="text-xs text-admin-text-tertiary">
                            Total
                        </p>
                        <p className="mt-1 text-xl font-semibold text-admin-text-primary tabular-nums sm:text-2xl">
                            {formatOrderMoney.format(order.total)}
                        </p>
                    </div>
                </div>
                <div className="mt-4 flex flex-wrap items-center gap-2 border-t border-admin-border pt-4">
                    <Badge className="border-admin-accent/30 bg-admin-accent/10 text-admin-accent-text">
                        {orderStatusLabels[order.status]}
                    </Badge>
                    <Badge
                        variant="outline"
                        className={
                            order.payment_status === 'paid'
                                ? 'border-success/40 bg-success-subtle text-success'
                                : order.payment_status === 'not_required'
                                  ? 'border-info/40 bg-info-subtle text-info'
                                  : undefined
                        }
                    >
                        {paymentStatusLabels[order.payment_status]}
                        {order.payment_method
                            ? ` · ${paymentMethodLabels[order.payment_method]}`
                            : ''}
                    </Badge>
                    <span className="text-sm text-admin-text-tertiary sm:ml-auto">
                        {orderConditionLabels[order.condition]}
                    </span>
                </div>
                <div className="mt-4 grid gap-2 sm:flex sm:flex-wrap">
                    <CopyOrderDetailButton
                        summary={summary}
                        className="w-full sm:w-auto"
                    />
                    {showFullDetailLink && (
                        <Button
                            asChild
                            variant="ghost"
                            className="text-admin-text-secondary hover:text-admin-text-primary"
                        >
                            <Link href={`/admin/orders/${order.id}`}>
                                Ver detalle completo <ExternalLink />
                            </Link>
                        </Button>
                    )}
                </div>
            </section>

            <DetailSection
                title={
                    order.customer === null && order.customer_name === null
                        ? 'Contexto del pedido'
                        : 'Cliente'
                }
            >
                {order.customer !== null ? (
                    <Link
                        href={`/admin/customers/${order.customer.id}`}
                        className="font-medium text-admin-text-primary underline-offset-4 hover:text-admin-accent-text hover:underline"
                    >
                        {formatOrderCustomer(order)}
                    </Link>
                ) : (
                    <p className="font-semibold text-admin-text-primary">
                        {formatOrderCustomer(order)}
                    </p>
                )}
            </DetailSection>

            <DetailSection title="Detalle del pedido">
                <div className="grid gap-4">
                    {order.items?.map((item, index) => (
                        <div
                            key={`${item.pricing_mode}-${index}`}
                            className="flex items-start justify-between gap-4 border-b border-border/70 pb-4 last:border-0 last:pb-0"
                        >
                            <div className="min-w-0">
                                <p className="font-medium text-admin-text-primary">
                                    {item.quantity} ×{' '}
                                    {pricingModeLabels[item.pricing_mode] ??
                                        item.pricing_mode}
                                    {item.product_name
                                        ? ` · ${item.product_name}`
                                        : ''}
                                </p>
                                <p className="mt-1 text-xs text-admin-text-tertiary">
                                    {item.units_count} unidades ·{' '}
                                    {formatOrderMoney.format(item.unit_price)}
                                </p>
                                {item.composition.length > 0 && (
                                    <ul className="mt-2 grid gap-1 text-sm text-admin-text-secondary">
                                        {item.composition.map((part) => (
                                            <li
                                                key={`${part.product_name}-${part.quantity}`}
                                            >
                                                {part.product_name} ×
                                                {part.quantity}
                                            </li>
                                        ))}
                                    </ul>
                                )}
                                {item.savings > 0 && (
                                    <p className="mt-2 text-xs text-success">
                                        Ahorro:{' '}
                                        {formatOrderMoney.format(item.savings)}
                                    </p>
                                )}
                            </div>
                            <span className="shrink-0 font-medium text-admin-text-primary tabular-nums">
                                {formatOrderMoney.format(item.subtotal)}
                            </span>
                        </div>
                    ))}
                </div>
            </DetailSection>

            {Boolean(order.toppings?.length) && (
                <DetailSection title="Adicionales">
                    <div className="grid gap-2 text-sm">
                        {order.toppings?.map((item) => (
                            <div
                                key={item.name}
                                className="flex justify-between gap-4"
                            >
                                <span>
                                    {item.name} ×{item.quantity}
                                </span>
                                <span className="tabular-nums">
                                    {formatOrderMoney.format(item.subtotal)}
                                </span>
                            </div>
                        ))}
                    </div>
                </DetailSection>
            )}

            <DetailSection title="Entrega">
                <div className="grid gap-2 text-sm">
                    <p className="font-medium text-admin-text-primary">
                        {deliveryTypeLabels[order.delivery_type]}
                    </p>
                    <p className="text-admin-text-secondary">
                        {formatOrderDate(order.delivery_date)} ·{' '}
                        {formatDeliveryPeriod(order.delivery_period)}
                        {order.delivery_time ? ` · ${order.delivery_time}` : ''}
                    </p>
                    {order.delivery_address && (
                        <p className="text-admin-text-secondary">
                            {order.delivery_address}
                        </p>
                    )}
                    {order.notes && (
                        <div className="mt-2 border-t border-border/70 pt-3">
                            <p className="mb-1 text-xs font-semibold tracking-wide text-admin-text-tertiary uppercase">
                                Notas
                            </p>
                            <p>{order.notes}</p>
                        </div>
                    )}
                </div>
            </DetailSection>

            <DetailSection title="Resumen del pedido">
                <div className="grid gap-2.5 text-sm">
                    <div className="flex justify-between gap-4">
                        <span>
                            {hasRawPricing
                                ? 'Productos cocinados'
                                : 'Productos'}
                        </span>
                        <span className="tabular-nums">
                            {formatOrderMoney.format(
                                order.items_cooked_subtotal ??
                                    order.items_subtotal,
                            )}
                        </span>
                    </div>
                    {(order.raw_discount ?? 0) > 0 && (
                        <div className="flex justify-between gap-4 text-success">
                            <span>Descuento por preparación en crudo</span>
                            <span className="tabular-nums">
                                −
                                {formatOrderMoney.format(
                                    order.raw_discount ?? 0,
                                )}
                            </span>
                        </div>
                    )}
                    <div className="flex justify-between gap-4">
                        <span>Adicionales</span>
                        <span className="tabular-nums">
                            {formatOrderMoney.format(order.toppings_subtotal)}
                        </span>
                    </div>
                    <div className="flex justify-between gap-4">
                        <span>Envío</span>
                        <span className="tabular-nums">
                            {formatOrderMoney.format(order.shipping_fee)}
                        </span>
                    </div>
                    {order.combined_dozen_savings > 0 && (
                        <div className="flex justify-between gap-4 text-success">
                            <span>Ahorro</span>
                            <span className="tabular-nums">
                                −
                                {formatOrderMoney.format(
                                    order.combined_dozen_savings,
                                )}
                            </span>
                        </div>
                    )}
                    {(order.discount_amount ?? 0) > 0 && (
                        <div className="flex justify-between gap-4 text-success">
                            <span>Descuento</span>
                            <span className="tabular-nums">
                                −
                                {formatOrderMoney.format(
                                    order.discount_amount ?? 0,
                                )}
                            </span>
                        </div>
                    )}
                    <div className="mt-2 flex justify-between border-t border-border pt-4 text-lg font-semibold text-admin-text-primary">
                        <span>Total</span>
                        <span className="tabular-nums">
                            {formatOrderMoney.format(order.total)}
                        </span>
                    </div>
                </div>
            </DetailSection>

            <DetailSection title="Operaciones">
                <div
                    className={
                        compact ? 'grid gap-3' : 'grid gap-3 sm:grid-cols-2'
                    }
                >
                    <label className="grid gap-2 text-xs text-admin-text-tertiary">
                        Estado
                        <Select
                            value={order.status}
                            onValueChange={(value) =>
                                changeStatus(value as OrderStatus)
                            }
                        >
                            <SelectTrigger aria-label="Estado del pedido">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent position="popper" side="top">
                                {orderStatuses.map((value) => (
                                    <SelectItem key={value} value={value}>
                                        {orderStatusLabels[value]}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </label>
                    <label className="grid gap-2 text-xs text-admin-text-tertiary">
                        Pago
                        <Select
                            value={paymentSelection}
                            onValueChange={(value) => {
                                const status = value as PaymentStatus;
                                setPaymentSelection(status);

                                if (status === 'unpaid') {
                                    setPaymentMethodSelection('');
                                    changePayment('unpaid', null);
                                } else if (paymentMethodSelection) {
                                    changePayment(
                                        'paid',
                                        paymentMethodSelection,
                                    );
                                }
                            }}
                        >
                            <SelectTrigger aria-label="Estado del pago">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent position="popper" side="top">
                                <SelectItem value="unpaid">
                                    {paymentStatusLabels.unpaid}
                                </SelectItem>
                                <SelectItem value="paid">
                                    {paymentStatusLabels.paid}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                    </label>
                    {paymentSelection === 'paid' && (
                        <label className="grid gap-2 text-xs text-admin-text-tertiary">
                            Medio de pago
                            <Select
                                value={paymentMethodSelection}
                                onValueChange={(value) => {
                                    const method = value as PaymentMethod;
                                    setPaymentMethodSelection(method);
                                    changePayment('paid', method);
                                }}
                            >
                                <SelectTrigger aria-label="Medio de pago">
                                    <SelectValue placeholder="Seleccioná un medio" />
                                </SelectTrigger>
                                <SelectContent position="popper" side="top">
                                    {Object.entries(paymentMethodLabels).map(
                                        ([value, label]) => (
                                            <SelectItem
                                                key={value}
                                                value={value}
                                            >
                                                {label}
                                            </SelectItem>
                                        ),
                                    )}
                                </SelectContent>
                            </Select>
                        </label>
                    )}
                </div>
                <div className="mt-4 flex flex-wrap gap-2">
                    <OrderEditButton
                        orderId={order.id}
                        version={order.version}
                        closed={closed}
                    />
                </div>
                {closed && (
                    <p className="mt-3 text-sm font-medium text-warning">
                        Pedido cerrado — edición bloqueada
                    </p>
                )}
            </DetailSection>
        </div>
    );
}
