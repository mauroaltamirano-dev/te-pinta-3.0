import { router } from '@inertiajs/react';
import { ArrowRight, LoaderCircle } from 'lucide-react';
import { useState } from 'react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet';
import { cn } from '@/lib/utils';
import {
    administratorName,
    formatManagementDate,
    formatManagementMoney,
    transactionLabel,
} from '../presentation';
import type { ManagementTransaction } from '../types';
import { AttachmentSection } from './attachment-section';

export function OrderPaymentDetailSheet({
    transaction,
    onOpenChange,
    onViewDistribution,
}: {
    transaction: ManagementTransaction | null;
    onOpenChange: (open: boolean) => void;
    onViewDistribution: (financialTransactionId: number) => void;
}) {
    const [applyingDistribution, setApplyingDistribution] = useState(false);
    const payment = transaction?.management_order_payment;

    if (!transaction || !payment) {
        return null;
    }

    const isReversal = transaction.type === 'reversal';
    const isReversed =
        payment.reversed || transaction.reversal_transaction_id !== null;
    const wallet = payment.destination_wallet;
    const distributionStatus = distributionStatusLabel(
        payment.distribution_status,
    );
    const applyDistribution = () => {
        setApplyingDistribution(true);
        router.post(
            `/admin/management/order-payments/${payment.id}/distribution`,
            {},
            {
                preserveScroll: true,
                onSuccess: () => onOpenChange(false),
                onFinish: () => setApplyingDistribution(false),
            },
        );
    };

    return (
        <Sheet open onOpenChange={onOpenChange}>
            <SheetContent className="admin-shell dark w-full overflow-y-auto border-admin-border bg-admin-surface sm:max-w-md [&>button:last-child]:z-10 [&>button:last-child]:flex [&>button:last-child]:size-11 [&>button:last-child]:items-center [&>button:last-child]:justify-center">
                <SheetHeader className="pointer-events-none border-b border-admin-border px-5 pt-6 pb-5">
                    <div className="flex flex-wrap items-center gap-2 pr-8">
                        <SheetTitle className="text-xl text-white">
                            {transactionLabel(transaction)}
                        </SheetTitle>
                        <Badge
                            variant={
                                isReversed && !isReversal
                                    ? 'destructive'
                                    : 'outline'
                            }
                            className={
                                isReversed && !isReversal
                                    ? undefined
                                    : 'border-admin-border text-admin-text-secondary'
                            }
                        >
                            {isReversal
                                ? 'Reversión'
                                : isReversed
                                  ? 'Revertido'
                                  : 'Confirmado'}
                        </Badge>
                    </div>
                    <SheetDescription className="text-admin-text-tertiary">
                        Ingreso financiero originado por un pedido confirmado
                        como pagado.
                    </SheetDescription>
                </SheetHeader>

                <div className="grid gap-6 px-5 py-5">
                    <div>
                        <p className="text-xs font-medium tracking-wide text-admin-text-tertiary uppercase">
                            Pedido
                        </p>
                        <p className="mt-1.5 text-base font-medium text-white">
                            {payment.order.public_id}
                        </p>
                        <p className="mt-1 text-sm text-admin-text-secondary">
                            {payment.order.customer_name ??
                                'Sin cliente asociado'}
                        </p>
                    </div>

                    <dl className="grid gap-4 sm:grid-cols-2">
                        <Detail
                            label="Importe"
                            value={formatManagementMoney.format(payment.amount)}
                            numeric
                        />
                        <Detail
                            label="Billetera destino"
                            value={`${wallet.name}${wallet.archived_at ? ' (archivada)' : ''}`}
                        />
                        <Detail
                            label="Fecha efectiva"
                            value={formatManagementDate(payment.effective_date)}
                        />
                        <Detail
                            label="Registrado por"
                            value={administratorName(transaction.created_by)}
                        />
                    </dl>

                    <AttachmentSection
                        transactionId={transaction.id}
                        attachmentsCount={transaction.attachments_count}
                    />

                    <section
                        aria-labelledby="order-payment-impact-title"
                        className="rounded-lg border border-admin-border bg-admin-surface-raised p-4"
                    >
                        <h3
                            id="order-payment-impact-title"
                            className="text-xs font-medium tracking-wide text-admin-text-tertiary uppercase"
                        >
                            Impacto financiero
                        </h3>
                        <ul className="mt-3 grid gap-2">
                            {transaction.entries.map((entry) => (
                                <li
                                    key={entry.id}
                                    className="flex items-center justify-between gap-3 text-sm"
                                >
                                    <span className="min-w-0 truncate text-admin-text-secondary">
                                        {entry.wallet.name}
                                        {entry.wallet.archived_at
                                            ? ' (archivada)'
                                            : ''}
                                    </span>
                                    <strong
                                        className={cn(
                                            'shrink-0 tabular-nums',
                                            entry.amount < 0
                                                ? 'text-destructive'
                                                : 'text-admin-accent',
                                        )}
                                    >
                                        {entry.amount >= 0 ? '+' : '−'}
                                        {formatManagementMoney.format(
                                            Math.abs(entry.amount),
                                        )}
                                    </strong>
                                </li>
                            ))}
                        </ul>
                    </section>

                    <section
                        aria-labelledby="order-payment-distribution-title"
                        className="rounded-lg border border-admin-border bg-admin-surface-raised p-4"
                    >
                        <div className="flex flex-wrap items-start justify-between gap-3">
                            <div>
                                <h3
                                    id="order-payment-distribution-title"
                                    className="text-xs font-medium tracking-wide text-admin-text-tertiary uppercase"
                                >
                                    Distribución
                                </h3>
                                <p className="mt-2 text-sm font-medium text-white">
                                    {distributionStatus}
                                </p>
                            </div>
                            <Badge
                                variant={
                                    payment.distribution_status === 'reversed'
                                        ? 'destructive'
                                        : 'outline'
                                }
                                className={
                                    payment.distribution_status === 'reversed'
                                        ? undefined
                                        : 'border-admin-border text-admin-text-secondary'
                                }
                            >
                                {distributionStatus}
                            </Badge>
                        </div>

                        {payment.distribution ? (
                            <div className="mt-4 grid gap-3">
                                <dl className="grid gap-3 sm:grid-cols-2">
                                    <Detail
                                        label="Regla"
                                        value={`${payment.distribution.rule.name} v${payment.distribution.rule_version.version_number}`}
                                    />
                                    <Detail
                                        label="Monto"
                                        value={formatManagementMoney.format(
                                            payment.amount,
                                        )}
                                        numeric
                                    />
                                </dl>
                                <Button
                                    type="button"
                                    variant="outline"
                                    onClick={() =>
                                        onViewDistribution(
                                            payment.distribution!
                                                .financial_transaction_id,
                                        )
                                    }
                                    className="h-11 w-full justify-between"
                                >
                                    Ver distribución
                                    <ArrowRight aria-hidden="true" />
                                </Button>
                            </div>
                        ) : (
                            <div className="mt-3 grid gap-3">
                                <p className="text-sm text-admin-text-secondary">
                                    {isReversed
                                        ? 'El pago fue revertido y ya no puede distribuirse.'
                                        : 'Este ingreso permanece en la billetera receptora hasta que exista una regla activa.'}
                                </p>
                                {!isReversed && (
                                    <Button
                                        type="button"
                                        variant="outline"
                                        disabled={applyingDistribution}
                                        onClick={applyDistribution}
                                        className="h-11 w-full"
                                    >
                                        {applyingDistribution && (
                                            <LoaderCircle className="animate-spin" />
                                        )}
                                        Distribuir ahora
                                    </Button>
                                )}
                            </div>
                        )}
                    </section>

                    {(isReversal || isReversed) && (
                        <p className="rounded-lg border border-admin-border p-4 text-sm text-admin-text-secondary">
                            {isReversal
                                ? 'Esta operación revierte el ingreso financiero del pedido original.'
                                : 'Este pago fue revertido y permanece visible para preservar la trazabilidad.'}
                        </p>
                    )}

                    <Button
                        type="button"
                        variant="outline"
                        onClick={() => onOpenChange(false)}
                        className="h-11 sm:hidden"
                    >
                        Cerrar detalle
                    </Button>
                </div>
            </SheetContent>
        </Sheet>
    );
}

function distributionStatusLabel(status: ManagementOrderPaymentStatus): string {
    switch (status) {
        case 'distributed':
            return 'Distribuido';
        case 'reversed':
            return 'Distribución revertida';
        default:
            return 'Pendiente de distribución';
    }
}

type ManagementOrderPaymentStatus = NonNullable<
    ManagementTransaction['management_order_payment']
>['distribution_status'];

function Detail({
    label,
    value,
    numeric = false,
}: {
    label: string;
    value: string;
    numeric?: boolean;
}) {
    return (
        <div>
            <dt className="text-xs font-medium text-admin-text-tertiary">
                {label}
            </dt>
            <dd
                className={`mt-1 text-sm text-white ${numeric ? 'font-semibold tabular-nums' : ''}`}
            >
                {value}
            </dd>
        </div>
    );
}
