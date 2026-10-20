import { Badge } from '@/components/ui/badge';
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

export function TransferDetailSheet({
    transaction,
    onOpenChange,
}: {
    transaction: ManagementTransaction | null;
    onOpenChange: (open: boolean) => void;
}) {
    const transfer = transaction?.management_transfer;

    if (!transaction || !transfer) {
        return null;
    }

    const isReversal = transaction.type === 'reversal';
    const isReversed =
        transfer.reversed || transaction.reversal_transaction_id !== null;
    const description = isReversal
        ? transaction.description || 'Reversión sin concepto'
        : transfer.description || 'Transferencia entre billeteras';

    return (
        <Sheet open onOpenChange={onOpenChange}>
            <SheetContent className="admin-shell w-full overflow-y-auto border-admin-border bg-admin-surface sm:max-w-md">
                <SheetHeader className="border-b border-admin-border px-5 pt-6 pb-5">
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
                                  : 'Activa'}
                        </Badge>
                    </div>
                    <SheetDescription className="text-admin-text-tertiary">
                        Redistribución interna e impactos sobre las billeteras.
                    </SheetDescription>
                </SheetHeader>

                <div className="grid gap-6 px-5 py-5">
                    <div>
                        <p className="text-xs font-medium tracking-wide text-admin-text-tertiary uppercase">
                            Descripción
                        </p>
                        <p className="mt-1.5 text-base font-medium text-admin-text-primary">
                            {description}
                        </p>
                        {isReversal && transfer.description && (
                            <p className="mt-1 text-sm text-admin-text-secondary">
                                Transferencia original: {transfer.description}
                            </p>
                        )}
                    </div>

                    <dl className="grid gap-4 sm:grid-cols-2">
                        <Detail
                            label="Origen"
                            value={walletName(transfer.source_wallet)}
                        />
                        <Detail
                            label="Destino"
                            value={walletName(transfer.destination_wallet)}
                        />
                        <Detail
                            label="Importe"
                            value={formatMoney(transfer.amount)}
                            numeric
                        />
                        <Detail
                            label="Fecha efectiva"
                            value={formatManagementDate(
                                transaction.effective_date,
                            )}
                        />
                        <Detail
                            label="Administrador"
                            value={administratorName(transaction.created_by)}
                        />
                        <Detail
                            label="Registrado"
                            value={formatManagementDate(transaction.created_at)}
                        />
                    </dl>

                    <AttachmentSection
                        transactionId={transaction.id}
                        attachmentsCount={transaction.attachments_count}
                    />

                    <section
                        aria-labelledby="transfer-impact-title"
                        className="rounded-lg border border-admin-border bg-admin-surface-raised p-4"
                    >
                        <h3
                            id="transfer-impact-title"
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
                                        {walletName(entry.wallet)}
                                    </span>
                                    <span
                                        className={cn(
                                            'shrink-0 font-semibold tabular-nums',
                                            entry.amount < 0
                                                ? 'text-destructive'
                                                : 'text-admin-accent-text',
                                        )}
                                    >
                                        {signedMoney(entry.amount)}
                                    </span>
                                </li>
                            ))}
                        </ul>
                    </section>

                    {(isReversal || isReversed) && (
                        <div className="rounded-lg border border-admin-border p-4 text-sm text-admin-text-secondary">
                            {isReversal
                                ? 'Esta operación revierte la transferencia original.'
                                : 'Esta transferencia fue revertida y permanece visible como parte del historial.'}
                        </div>
                    )}
                </div>
            </SheetContent>
        </Sheet>
    );
}

function walletName(wallet: { name: string; archived_at: string | null }) {
    return `${wallet.name}${wallet.archived_at ? ' (archivada)' : ''}`;
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
