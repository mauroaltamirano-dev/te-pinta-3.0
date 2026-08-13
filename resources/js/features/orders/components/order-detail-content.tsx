import { Link, router } from '@inertiajs/react';
import { Check, Clipboard, ExternalLink, Pencil } from 'lucide-react';
import { useState } from 'react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import {
    deliveryPeriodLabels,
    deliveryTypeLabels,
    formatOrderMoney,
    orderConditionLabels,
    orderStatusLabels,
    orderStatuses,
    paymentStatusLabels,
    pricingModeLabels,
} from '@/features/orders/presentation';
import type {
    AdminOrder,
    OrderStatus,
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
        <section className="rounded-lg bg-admin-surface-raised p-4 sm:p-5">
            <h2 className="mb-4 text-sm font-semibold text-white">{title}</h2>
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
    const [copied, setCopied] = useState(false);
    const [enableEditOpen, setEnableEditOpen] = useState(false);
    const [enableEditReason, setEnableEditReason] = useState('');
    const [enablingEdit, setEnablingEdit] = useState(false);
    const closed = order.status === 'delivered' || order.status === 'cancelled';
    const changeStatus = (status: OrderStatus) =>
        router.patch(
            `/admin/orders/${order.id}/status`,
            { status },
            { preserveScroll: true },
        );
    const changePayment = (payment_status: PaymentStatus) =>
        router.patch(
            `/admin/orders/${order.id}/payment-status`,
            { payment_status },
            { preserveScroll: true },
        );
    const copy = async () => {
        try {
            await navigator.clipboard.writeText(summary);
        } catch {
            const textarea = document.createElement('textarea');
            textarea.value = summary;
            textarea.style.position = 'fixed';
            textarea.style.opacity = '0';
            document.body.appendChild(textarea);
            textarea.select();
            document.execCommand('copy');
            textarea.remove();
        }

        setCopied(true);
        window.setTimeout(() => setCopied(false), 2000);
    };

    const hasCookingBreakdown = order.items_base_subtotal !== null;
    const baseSubtotal = order.items_base_subtotal ?? order.items_subtotal;
    const cookingSurcharge = order.cooking_surcharge ?? 0;

    return (
        <div className="grid gap-4 font-orders">
            <div className="flex flex-wrap items-center gap-2">
                <Badge className="border-admin-accent/30 bg-admin-accent/10 text-admin-accent">
                    {orderStatusLabels[order.status]}
                </Badge>
                <Badge variant="outline">
                    {paymentStatusLabels[order.payment_status]}
                </Badge>
                <span className="ml-auto text-sm text-admin-text-tertiary">
                    {orderConditionLabels[order.condition]}
                </span>
            </div>

            <DetailSection title="Cliente">
                <Link
                    href={`/admin/customers/${order.customer.id}`}
                    className="font-medium text-white underline-offset-4 hover:text-admin-accent hover:underline"
                >
                    {order.customer.name}
                </Link>
            </DetailSection>

            <DetailSection title="Detalle del pedido">
                <div className="grid gap-4">
                    {order.items?.map((item, index) => (
                        <div
                            key={`${item.pricing_mode}-${index}`}
                            className="flex items-start justify-between gap-4 border-b border-border/70 pb-4 last:border-0 last:pb-0"
                        >
                            <div className="min-w-0">
                                <p className="font-medium text-white">
                                    {item.quantity} ×{' '}
                                    {pricingModeLabels[item.pricing_mode] ??
                                        item.pricing_mode}
                                    {item.product_name
                                        ? ` · ${item.product_name}`
                                        : ''}
                                </p>
                                <p className="mt-1 text-xs text-admin-text-tertiary">
                                    {item.units_count} empanadas ·{' '}
                                    {formatOrderMoney.format(item.unit_price)}
                                </p>
                                {item.composition.length > 0 && (
                                    <ul className="mt-2 grid gap-1 text-sm text-muted-foreground">
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
                                    <p className="mt-2 text-xs text-emerald-400">
                                        Ahorro:{' '}
                                        {formatOrderMoney.format(item.savings)}
                                    </p>
                                )}
                            </div>
                            <span className="shrink-0 font-medium text-white tabular-nums">
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
                    <p className="font-medium text-white">
                        {deliveryTypeLabels[order.delivery_type]}
                    </p>
                    <p className="text-muted-foreground">
                        {order.delivery_date} ·{' '}
                        {deliveryPeriodLabels[order.delivery_period]}
                        {order.delivery_time ? ` · ${order.delivery_time}` : ''}
                    </p>
                    {order.delivery_address && (
                        <p className="text-muted-foreground">
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
                            {hasCookingBreakdown
                                ? 'Empanadas base'
                                : 'Empanadas'}
                        </span>
                        <span className="tabular-nums">
                            {formatOrderMoney.format(baseSubtotal)}
                        </span>
                    </div>
                    {hasCookingBreakdown && order.condition === 'cooked' && (
                        <div className="flex justify-between gap-4">
                            <span>Cocción</span>
                            <span className="tabular-nums">
                                +{formatOrderMoney.format(cookingSurcharge)}
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
                        <div className="flex justify-between gap-4 text-emerald-400">
                            <span>Ahorro</span>
                            <span className="tabular-nums">
                                −
                                {formatOrderMoney.format(
                                    order.combined_dozen_savings,
                                )}
                            </span>
                        </div>
                    )}
                    <div className="mt-2 flex justify-between border-t border-border pt-4 text-lg font-semibold text-white">
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
                            value={order.payment_status}
                            onValueChange={(value) =>
                                changePayment(value as PaymentStatus)
                            }
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
                </div>
                <div className="mt-4 flex flex-wrap gap-2">
                    {closed ? (
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => setEnableEditOpen(true)}
                        >
                            <Pencil /> Habilitar edición
                        </Button>
                    ) : (
                        <Button asChild type="button">
                            <Link href={`/admin/orders/${order.id}/edit`}>
                                <Pencil /> Editar pedido
                            </Link>
                        </Button>
                    )}
                    <Button type="button" variant="outline" onClick={copy}>
                        {copied ? <Check /> : <Clipboard />}
                        {copied ? 'Detalle copiado' : 'Copiar detalle'}
                    </Button>
                    {showFullDetailLink && (
                        <Button asChild variant="ghost">
                            <Link href={`/admin/orders/${order.id}`}>
                                Ver detalle completo <ExternalLink />
                            </Link>
                        </Button>
                    )}
                </div>
                {closed && (
                    <p className="mt-3 text-sm font-medium text-amber-400">
                        Pedido cerrado — edición bloqueada
                    </p>
                )}
            </DetailSection>

            <Dialog open={enableEditOpen} onOpenChange={setEnableEditOpen}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>
                            Habilitar edición de {order.id}
                        </DialogTitle>
                        <DialogDescription>
                            Vas a modificar un pedido cerrado. Su estado no
                            cambiará automáticamente y la acción quedará
                            registrada.
                        </DialogDescription>
                    </DialogHeader>
                    <div className="grid gap-2">
                        <Label htmlFor={`closed-edit-reason-${order.id}`}>
                            Motivo breve
                        </Label>
                        <Textarea
                            id={`closed-edit-reason-${order.id}`}
                            value={enableEditReason}
                            maxLength={240}
                            onChange={(event) =>
                                setEnableEditReason(event.target.value)
                            }
                            placeholder="Indicá por qué necesitás corregirlo"
                        />
                    </div>
                    <DialogFooter>
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => setEnableEditOpen(false)}
                        >
                            Volver
                        </Button>
                        <Button
                            type="button"
                            disabled={
                                enablingEdit ||
                                enableEditReason.trim().length < 3
                            }
                            onClick={() => {
                                setEnablingEdit(true);
                                router.post(
                                    `/admin/orders/${order.id}/enable-edit`,
                                    {
                                        reason: enableEditReason.trim(),
                                        expected_version: order.version,
                                    },
                                    {
                                        onFinish: () => setEnablingEdit(false),
                                    },
                                );
                            }}
                        >
                            Confirmar y editar
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </div>
    );
}
