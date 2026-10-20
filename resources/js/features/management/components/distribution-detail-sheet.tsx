import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet';
import { formatMoney } from '@/lib/format';
import { cn } from '@/lib/utils';
import {
    administratorName,
    formatManagementDate,
    transactionLabel,
} from '../presentation';
import type { ManagementTransaction } from '../types';
import { AttachmentSection } from './attachment-section';

export function DistributionDetailSheet({
    transaction,
    onOpenChange,
    onViewOrderPayment,
}: {
    transaction: ManagementTransaction | null;
    onOpenChange: (open: boolean) => void;
    onViewOrderPayment: (financialTransactionId: number) => void;
}) {
    const distribution = transaction?.management_distribution;

    if (!transaction || !distribution) {
        return null;
    }

    const isReversal = transaction.type === 'reversal';
    const isReversed =
        distribution.reversed || transaction.reversal_transaction_id !== null;
    const ruleName =
        distribution.rule?.name ?? distribution.rule_name ?? 'Regla';
    const versionNumber =
        distribution.rule_version?.version_number ??
        distribution.rule_version_number;
    const sourceEntry = transaction.entries.find(
        (entry) => entry.wallet.id === distribution.source_wallet.id,
    );

    return (
        <Sheet open onOpenChange={onOpenChange}>
            <SheetContent className="admin-shell w-full overflow-y-auto border-admin-border bg-admin-surface sm:max-w-md [&>button:last-child]:z-10 [&>button:last-child]:flex [&>button:last-child]:size-11 [&>button:last-child]:items-center [&>button:last-child]:justify-center">
                <SheetHeader className="pointer-events-none border-b border-admin-border px-5 pt-6 pb-5">
                    <div className="flex flex-wrap items-center gap-2 pr-8">
                        <SheetTitle className="text-xl text-admin-text-primary">
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
                                  ? 'Revertida'
                                  : 'Aplicada'}
                        </Badge>
                    </div>
                    <SheetDescription className="text-admin-text-tertiary">
                        Snapshot de la regla aplicada y sus impactos
                        financieros.
                    </SheetDescription>
                </SheetHeader>

                <div className="grid gap-6 px-5 py-5">
                    <div>
                        <p className="text-xs font-medium tracking-wide text-admin-text-tertiary uppercase">
                            Regla aplicada
                        </p>
                        <p className="mt-1.5 text-base font-medium text-admin-text-primary">
                            {ruleName}
                            {versionNumber ? ` v${versionNumber}` : ''}
                        </p>
                        {distribution.description && (
                            <p className="mt-1 text-sm text-admin-text-secondary">
                                {distribution.description}
                            </p>
                        )}
                    </div>

                    {distribution.management_order_payment && (
                        <section
                            aria-labelledby="distribution-order-payment-title"
                            className="rounded-lg border border-admin-border bg-admin-surface-raised p-4"
                        >
                            <h3
                                id="distribution-order-payment-title"
                                className="text-xs font-medium tracking-wide text-admin-text-tertiary uppercase"
                            >
                                Origen del negocio
                            </h3>
                            <p className="mt-2 text-sm font-medium text-admin-text-primary">
                                Pago de pedido{' '}
                                {
                                    distribution.management_order_payment.order
                                        .public_id
                                }
                            </p>
                            <p className="mt-1 text-sm text-admin-text-secondary">
                                Esta asignación usa la billetera receptora del
                                pago como origen financiero.
                            </p>
                            <Button
                                type="button"
                                variant="outline"
                                onClick={() =>
                                    onViewOrderPayment(
                                        distribution.management_order_payment!
                                            .financial_transaction_id,
                                    )
                                }
                                className="mt-4 h-11 w-full"
                            >
                                Ver pago de pedido
                            </Button>
                        </section>
                    )}

                    <dl className="grid gap-4 sm:grid-cols-2">
                        <Detail
                            label="Monto"
                            value={formatMoney(distribution.amount)}
                            numeric
                        />
                        <Detail
                            label="Fecha efectiva"
                            value={formatManagementDate(
                                distribution.effective_date,
                            )}
                        />
                        <Detail
                            label="Administrador"
                            value={administratorName(transaction.created_by)}
                        />
                        <Detail
                            label="Registrado"
                            value={formatManagementDate(
                                distribution.created_at,
                            )}
                        />
                    </dl>

                    <AttachmentSection
                        transactionId={transaction.id}
                        attachmentsCount={transaction.attachments_count}
                    />

                    <section
                        aria-labelledby="distribution-allocation-title"
                        className="rounded-lg border border-admin-border bg-admin-surface-raised p-4"
                    >
                        <h3
                            id="distribution-allocation-title"
                            className="text-xs font-medium tracking-wide text-admin-text-tertiary uppercase"
                        >
                            Impacto financiero
                        </h3>
                        <ul className="mt-3 grid gap-3">
                            <li className="grid grid-cols-[minmax(0,1fr)_auto] gap-x-3 border-b border-admin-border pb-3 text-sm">
                                <span className="min-w-0 truncate text-admin-text-secondary">
                                    {distribution.source_wallet.name}
                                    {distribution.source_wallet.archived_at
                                        ? ' (archivada)'
                                        : ''}
                                </span>
                                <strong
                                    className={cn(
                                        'text-right tabular-nums',
                                        (sourceEntry?.amount ??
                                            -distribution.amount) < 0
                                            ? 'text-destructive'
                                            : 'text-admin-accent-text',
                                    )}
                                >
                                    {signedMoney(
                                        sourceEntry?.amount ??
                                            -distribution.amount,
                                    )}
                                </strong>
                                <span className="text-xs text-admin-text-tertiary">
                                    Origen
                                </span>
                            </li>
                            {distribution.allocations.map((allocation) => {
                                const entry = transaction.entries.find(
                                    (candidate) =>
                                        candidate.wallet.id ===
                                        allocation.wallet.id,
                                );
                                const amount =
                                    entry?.amount ?? allocation.amount ?? 0;

                                return (
                                    <li
                                        key={
                                            allocation.id ??
                                            allocation.wallet.id
                                        }
                                        className="grid grid-cols-[minmax(0,1fr)_auto] gap-x-3 gap-y-0.5 text-sm"
                                    >
                                        <span className="min-w-0 truncate text-admin-text-secondary">
                                            {allocation.wallet_name ??
                                                allocation.wallet.name}
                                            {allocation.wallet.archived_at
                                                ? ' (archivada)'
                                                : ''}
                                        </span>
                                        <strong
                                            className={cn(
                                                'text-right tabular-nums',
                                                amount < 0
                                                    ? 'text-destructive'
                                                    : 'text-admin-accent-text',
                                            )}
                                        >
                                            {signedMoney(amount)}
                                        </strong>
                                        <span className="text-xs text-admin-text-tertiary">
                                            {(
                                                allocation.percentage_bps / 100
                                            ).toLocaleString('es-AR', {
                                                maximumFractionDigits: 2,
                                            })}
                                            %
                                        </span>
                                    </li>
                                );
                            })}
                        </ul>
                    </section>

                    {(isReversal || isReversed) && (
                        <p className="rounded-lg border border-admin-border p-4 text-sm text-admin-text-secondary">
                            {isReversal
                                ? 'Esta operación revierte todos los impactos de la distribución original.'
                                : 'Esta distribución fue revertida y permanece visible con su versión original.'}
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

function signedMoney(amount: number) {
    return `${amount >= 0 ? '+' : '−'}${formatMoney(Math.abs(amount))}`;
}

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
                className={`mt-1 text-sm text-admin-text-primary ${numeric ? 'font-semibold tabular-nums' : ''}`}
            >
                {value}
            </dd>
        </div>
    );
}
