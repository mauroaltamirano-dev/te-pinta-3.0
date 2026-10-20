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
import { paymentMethodLabels } from '@/features/orders/presentation';
import type { PaymentMethod } from '@/features/orders/types';
import type { TableDetail } from '@/features/tables/types';
import { formatMoney } from '@/lib/format';
import { cn } from '@/lib/utils';

type Session = NonNullable<TableDetail['session']>;

/** Waiter: ask for the bill. Owner, manager, cashier: print the bill, charge or cancel. */
export function TableSessionActions({
    detail,
    session,
}: {
    detail: TableDetail;
    session: Session;
}) {
    const [processing, setProcessing] = useState(false);
    const [charging, setCharging] = useState(false);
    const [cancelling, setCancelling] = useState(false);
    const [method, setMethod] = useState<PaymentMethod | null>(null);

    function post(action: string, data: Record<string, string> = {}) {
        setProcessing(true);
        router.post(`/admin/table-sessions/${session.id}/${action}`, data, {
            preserveScroll: true,
            onFinish: () => {
                setProcessing(false);
                setCharging(false);
                setCancelling(false);
            },
        });
    }

    return (
        <section
            aria-label="Cuenta"
            className="grid gap-2 sm:grid-cols-2 lg:grid-cols-4"
        >
            {session.can_act && !session.bill_requested && (
                <Button
                    type="button"
                    variant="outline"
                    className="h-12"
                    disabled={processing}
                    onClick={() => post('bill-request')}
                >
                    Pedir la cuenta
                </Button>
            )}
            {detail.can_cashier && (
                <>
                    <Button
                        type="button"
                        variant="outline"
                        className="h-12"
                        disabled={processing}
                        onClick={() => post('bill')}
                    >
                        Imprimir cuenta
                    </Button>
                    <Button
                        type="button"
                        className="h-12"
                        disabled={processing}
                        onClick={() => {
                            setMethod(null);
                            setCharging(true);
                        }}
                    >
                        Cobrar
                    </Button>
                    <Button
                        type="button"
                        variant="ghost"
                        className="h-12 text-destructive"
                        disabled={processing}
                        onClick={() => setCancelling(true)}
                    >
                        Cancelar mesa
                    </Button>
                </>
            )}

            <Dialog
                open={charging}
                onOpenChange={(open) => !processing && setCharging(open)}
            >
                <DialogContent className="admin-shell">
                    <DialogHeader>
                        <DialogTitle>
                            Cobrar mesa {detail.table.code}
                        </DialogTitle>
                        <DialogDescription>
                            Cobrá cuando el dinero esté contado. La mesa se
                            cierra y su cuenta ya no cambia.
                        </DialogDescription>
                    </DialogHeader>
                    <p className="text-3xl font-semibold tabular-nums">
                        {formatMoney(session.total)}
                    </p>
                    {detail.missing_consumption.length > 0 && (
                        <p
                            role="alert"
                            className="rounded-lg bg-warning-subtle p-3 text-sm text-warning"
                        >
                            Falta configurar el consumo de inventario de:{' '}
                            {detail.missing_consumption.join(', ')}. Sin eso no
                            se puede cobrar.
                        </p>
                    )}
                    <div
                        role="group"
                        aria-label="Medio de pago"
                        className="grid grid-cols-3 gap-2"
                    >
                        {detail.payment_methods.map((value) => (
                            <button
                                key={value}
                                type="button"
                                aria-pressed={method === value}
                                onClick={() => setMethod(value)}
                                className={cn(
                                    'h-12 rounded-lg border border-admin-border text-sm font-medium',
                                    method === value &&
                                        'border-admin-accent bg-admin-accent text-admin-on-accent',
                                )}
                            >
                                {paymentMethodLabels[value]}
                            </button>
                        ))}
                    </div>
                    <DialogFooter className="gap-2">
                        <Button
                            type="button"
                            variant="outline"
                            className="h-11"
                            disabled={processing}
                            onClick={() => setCharging(false)}
                        >
                            Volver
                        </Button>
                        <Button
                            type="button"
                            className="h-11"
                            disabled={method === null || processing}
                            onClick={() =>
                                method &&
                                post('charge', { payment_method: method })
                            }
                        >
                            Confirmar cobro
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            <Dialog
                open={cancelling}
                onOpenChange={(open) => !processing && setCancelling(open)}
            >
                <DialogContent className="admin-shell">
                    <DialogHeader>
                        <DialogTitle>
                            ¿Cancelar la mesa {detail.table.code}?
                        </DialogTitle>
                        <DialogDescription>
                            El pedido queda cancelado en el historial y la mesa
                            se libera. No se cobra nada.
                        </DialogDescription>
                    </DialogHeader>
                    <DialogFooter className="gap-2">
                        <Button
                            type="button"
                            variant="outline"
                            className="h-11"
                            disabled={processing}
                            onClick={() => setCancelling(false)}
                        >
                            Volver
                        </Button>
                        <Button
                            type="button"
                            variant="destructive"
                            className="h-11"
                            disabled={processing}
                            onClick={() => post('cancel')}
                        >
                            Sí, cancelar mesa
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </section>
    );
}
