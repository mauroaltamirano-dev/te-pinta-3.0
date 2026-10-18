import { Badge } from '@/components/ui/badge';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet';
import {
    administratorName,
    formatManagementDate,
    formatManagementMoney,
    transactionLabel,
} from '../presentation';
import type { ManagementTransaction } from '../types';
import { AttachmentSection } from './attachment-section';

export function OutflowDetailSheet({
    transaction,
    onOpenChange,
}: {
    transaction: ManagementTransaction | null;
    onOpenChange: (open: boolean) => void;
}) {
    const outflow = transaction?.management_outflow;

    if (!transaction || !outflow) {
        return null;
    }

    const wallet = transaction.entries[0]?.wallet;
    const isReversed =
        outflow.reversed || transaction.reversal_transaction_id !== null;
    const isReversal = transaction.type === 'reversal';

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
                                  ? 'Revertido'
                                  : 'Registrado'}
                        </Badge>
                    </div>
                    <SheetDescription className="text-admin-text-tertiary">
                        Detalle administrativo y referencia del impacto
                        financiero.
                    </SheetDescription>
                </SheetHeader>

                <div className="grid gap-6 px-5 py-5">
                    <div>
                        <p className="text-xs font-medium tracking-wide text-admin-text-tertiary uppercase">
                            Concepto
                        </p>
                        <p className="mt-1.5 text-base font-medium text-admin-text-primary">
                            {outflow.description}
                        </p>
                        {outflow.counterparty && (
                            <p className="mt-1 text-sm text-admin-text-secondary">
                                {outflow.counterparty}
                            </p>
                        )}
                    </div>

                    <dl className="grid gap-4 sm:grid-cols-2">
                        <Detail
                            label="Categoría"
                            value={outflow.category.name}
                        />
                        <Detail
                            label="Billetera"
                            value={
                                wallet
                                    ? `${wallet.name}${wallet.archived_at ? ' (archivada)' : ''}`
                                    : 'No disponible'
                            }
                        />
                        <Detail
                            label="Importe"
                            value={formatManagementMoney.format(outflow.amount)}
                            numeric
                        />
                        <Detail
                            label="Fecha efectiva"
                            value={formatManagementDate(outflow.effective_date)}
                        />
                        <Detail
                            label="Administrador"
                            value={administratorName(transaction.created_by)}
                        />
                        <Detail
                            label="Registrado"
                            value={formatManagementDate(outflow.created_at)}
                        />
                    </dl>

                    <AttachmentSection
                        transactionId={transaction.id}
                        attachmentsCount={transaction.attachments_count}
                    />

                    <div className="rounded-lg border border-admin-border bg-admin-surface-raised p-4">
                        <p className="text-xs font-medium tracking-wide text-admin-text-tertiary uppercase">
                            Referencia financiera
                        </p>
                        <p className="mt-2 text-sm text-admin-text-secondary">
                            Transacción #{transaction.id}
                        </p>
                        {transaction.reversal_transaction_id && (
                            <p className="mt-1 text-sm text-admin-text-secondary">
                                Revertida por transacción #
                                {transaction.reversal_transaction_id}
                            </p>
                        )}
                    </div>
                </div>
            </SheetContent>
        </Sheet>
    );
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
