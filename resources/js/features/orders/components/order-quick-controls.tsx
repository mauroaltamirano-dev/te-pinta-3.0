import { router } from '@inertiajs/react';
import { useState } from 'react';
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
import { cn } from '@/lib/utils';
import {
    orderStatusLabels,
    orderStatusStyles,
    orderStatuses,
    paymentMethodLabels,
} from '../presentation';
import type { AdminOrder, OrderStatus, PaymentMethod } from '../types';

export function OrderStatusSelect({ order }: { order: AdminOrder }) {
    const [processing, setProcessing] = useState(false);

    return (
        <Select
            value={order.status}
            disabled={processing}
            onValueChange={(status: OrderStatus) => {
                setProcessing(true);
                router.patch(
                    `/admin/orders/${order.id}/status`,
                    { status },
                    {
                        preserveScroll: true,
                        onFinish: () => setProcessing(false),
                    },
                );
            }}
        >
            <SelectTrigger
                aria-label={`Estado de ${order.id}`}
                className={cn(
                    'h-10 min-w-40 border-transparent text-xs font-semibold',
                    orderStatusStyles[order.status],
                )}
            >
                <SelectValue />
            </SelectTrigger>
            <SelectContent>
                {orderStatuses.map((status) => (
                    <SelectItem key={status} value={status}>
                        <span
                            className={cn(
                                'rounded-md px-2 py-1 text-xs font-semibold',
                                orderStatusStyles[status],
                            )}
                        >
                            {orderStatusLabels[status]}
                        </span>
                    </SelectItem>
                ))}
            </SelectContent>
        </Select>
    );
}

export function OrderPaymentToggle({ order }: { order: AdminOrder }) {
    const paid = order.payment_status === 'paid';
    const [methodOpen, setMethodOpen] = useState(false);
    const [method, setMethod] = useState<PaymentMethod | ''>('');
    const [processing, setProcessing] = useState(false);
    const updatePayment = (
        payment_status: 'paid' | 'unpaid',
        payment_method: PaymentMethod | null,
    ) => {
        setProcessing(true);
        router.patch(
            `/admin/orders/${order.id}/payment-status`,
            { payment_status, payment_method },
            {
                preserveScroll: true,
                onSuccess: () => {
                    setMethodOpen(false);
                    setMethod('');
                },
                onFinish: () => setProcessing(false),
            },
        );
    };

    if (order.total === 0) {
        return (
            <span className="inline-flex min-h-11 items-center rounded-lg bg-info-subtle px-3 text-xs font-semibold text-info">
                No requiere pago
            </span>
        );
    }

    return (
        <>
            <button
                type="button"
                role="switch"
                aria-checked={paid}
                aria-label={`${paid ? 'Marcar como no pagado' : 'Marcar como pagado'} ${order.id}`}
                disabled={processing}
                onClick={() => {
                    if (paid) {
                        updatePayment('unpaid', null);
                    } else {
                        setMethodOpen(true);
                    }
                }}
                className="group flex min-h-11 items-center gap-2 rounded-lg px-1 text-xs font-medium text-admin-text-secondary disabled:opacity-50"
            >
                <span
                    aria-hidden="true"
                    className={cn(
                        'relative block h-6 w-11 rounded-full bg-admin-surface-raised ring-1 ring-admin-border transition-colors',
                        paid && 'bg-success ring-success',
                    )}
                >
                    <span
                        className={cn(
                            'absolute top-1 left-1 size-4 rounded-full bg-admin-surface shadow transition-transform',
                            paid && 'translate-x-5',
                        )}
                    />
                </span>
                <span>{paid ? 'Pagado' : 'No pagado'}</span>
            </button>

            <Dialog open={methodOpen} onOpenChange={setMethodOpen}>
                <DialogContent className="admin-shell border-admin-border bg-admin-surface font-orders text-admin-text-primary shadow-2xl sm:max-w-md [&>button]:text-admin-text-secondary [&>button:hover]:text-admin-text-primary">
                    <DialogHeader className="gap-3 border-b border-admin-border pb-4">
                        <p className="text-xs font-semibold text-admin-accent-text uppercase">
                            Cobro del pedido
                        </p>
                        <DialogTitle className="text-xl text-admin-text-primary">
                            Marcar {order.id} como pagado
                        </DialogTitle>
                        <DialogDescription className="text-admin-text-secondary">
                            Elegí cómo se recibió el pago. El pedido no se
                            marcará como pagado hasta confirmarlo.
                        </DialogDescription>
                    </DialogHeader>
                    <div className="grid gap-3 rounded-xl border border-admin-border bg-admin-surface-raised p-4">
                        <Label htmlFor={`payment-method-${order.id}`}>
                            Medio de pago
                        </Label>
                        <Select
                            value={method}
                            onValueChange={(value: PaymentMethod) =>
                                setMethod(value)
                            }
                        >
                            <SelectTrigger
                                id={`payment-method-${order.id}`}
                                aria-label={`Medio de pago de ${order.id}`}
                                className="h-11 w-full border-admin-border !bg-admin-canvas text-admin-text-primary focus-visible:border-admin-accent focus-visible:ring-admin-accent/30"
                            >
                                <SelectValue placeholder="Seleccioná un medio" />
                            </SelectTrigger>
                            <SelectContent className="admin-shell border-admin-border !bg-admin-surface-raised !text-admin-text-primary">
                                {Object.entries(paymentMethodLabels).map(
                                    ([value, label]) => (
                                        <SelectItem
                                            key={value}
                                            value={value}
                                            className="focus:bg-admin-selection focus:text-admin-text-primary data-[state=checked]:text-admin-accent-text"
                                        >
                                            {label}
                                        </SelectItem>
                                    ),
                                )}
                            </SelectContent>
                        </Select>
                    </div>
                    <DialogFooter className="border-t border-admin-border pt-4">
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => setMethodOpen(false)}
                        >
                            Cancelar
                        </Button>
                        <Button
                            type="button"
                            disabled={!method || processing}
                            onClick={() =>
                                method && updatePayment('paid', method)
                            }
                        >
                            Confirmar pago
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </>
    );
}
