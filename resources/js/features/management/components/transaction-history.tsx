import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { cn } from '@/lib/utils';
import { Link, router, usePage } from '@inertiajs/react';
import {
    ChevronLeft,
    ChevronRight,
    Eye,
    Paperclip,
    RotateCcw,
} from 'lucide-react';
import type { FormEvent } from 'react';
import { useState } from 'react';
import {
    administratorName,
    formatManagementDate,
    formatManagementMoney,
    outflowTypeLabels,
    transactionLabel,
} from '../presentation';
import type {
    ManagementFilters,
    ManagementMovementTypeOption,
    ManagementOutflowCategory,
    ManagementOutflowTypeOption,
    ManagementTransaction,
    ManagementWallet,
    PaginatedManagementTransactions,
} from '../types';
import { DistributionDetailSheet } from './distribution-detail-sheet';
import { OrderPaymentDetailSheet } from './order-payment-detail-sheet';
import { OutflowDetailSheet } from './outflow-detail-sheet';
import { TransferDetailSheet } from './transfer-detail-sheet';

export function TransactionHistory({
    transactions,
    wallets,
    filters,
    movementTypes,
    outflowTypes,
    categories,
    onReverse,
}: {
    transactions: PaginatedManagementTransactions;
    wallets: ManagementWallet[];
    filters: ManagementFilters;
    movementTypes: ManagementMovementTypeOption[];
    outflowTypes: ManagementOutflowTypeOption[];
    categories: ManagementOutflowCategory[];
    onReverse: (transaction: ManagementTransaction) => void;
}) {
    const { url } = usePage();
    const [walletId, setWalletId] = useState(
        filters.wallet_id?.toString() ?? 'all',
    );
    const [type, setType] = useState(filters.type ?? 'all');
    const [categoryId, setCategoryId] = useState(
        filters.category_id?.toString() ?? 'all',
    );
    const [from, setFrom] = useState(filters.history_from ?? '');
    const [to, setTo] = useState(filters.history_to ?? '');
    const [selectedDetail, setSelectedDetail] =
        useState<ManagementTransaction | null>(null);
    const viewRelatedTransaction = (
        financialTransactionId: number,
        fallbackType: 'order_payment' | 'distribution',
    ) => {
        const related = transactions.data.find(
            (transaction) => transaction.id === financialTransactionId,
        );

        if (related) {
            setSelectedDetail(related);

            return;
        }

        setSelectedDetail(null);
        router.get(
            '/admin/management',
            {
                transaction_id: financialTransactionId,
                section: 'movements',
                type: fallbackType,
            },
            { preserveState: true, preserveScroll: true },
        );
    };
    const applyFilters = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        router.get(
            '/admin/management',
            {
                ...Object.fromEntries(
                    new URL(url, 'http://localhost').searchParams,
                ),
                section: 'movements',
                wallet_id: walletId === 'all' ? null : walletId,
                type: type === 'all' ? null : type,
                category_id: categoryId === 'all' ? null : categoryId,
                page: null,
                transaction_id: null,
                history_period: 'custom',
                ...(walletId !== 'all' ? { wallet_id: walletId } : {}),
                ...(type !== 'all' ? { type } : {}),
                ...(categoryId !== 'all' ? { category_id: categoryId } : {}),
                ...(from ? { history_from: from } : {}),
                ...(to ? { history_to: to } : {}),
            },
            { preserveState: true, replace: true, preserveScroll: true },
        );
    };
    const clearFilters = () => {
        setWalletId('all');
        setType('all');
        setCategoryId('all');
        setFrom('');
        setTo('');
        router.get(
            '/admin/management',
            {
                period: filters.period,
                from: filters.from,
                to: filters.to,
                section: 'movements',
            },
            { preserveState: false, preserveScroll: true },
        );
    };
    const filterTypes = [
        ...movementTypes,
        ...outflowTypes,
        { value: 'transfer' as const, label: 'Transferencia' },
        { value: 'distribution' as const, label: 'Distribución' },
        { value: 'order_payment' as const, label: 'Pago de pedido' },
        { value: 'reversal' as const, label: 'Reversión' },
    ].filter(
        (option, index, options) =>
            options.findIndex(
                (candidate) => candidate.value === option.value,
            ) === index,
    );

    return (
        <section
            data-testid="management-history-group"
            className="overflow-hidden rounded-xl border border-admin-border bg-admin-surface"
        >
            <header className="border-b border-admin-border p-4 sm:p-5">
                <h2 className="text-xl font-semibold text-white">
                    Movimientos
                </h2>
                <p className="mt-1 text-sm text-admin-text-tertiary">
                    Historial inmutable de impactos sobre las billeteras.
                </p>
                <form
                    onSubmit={applyFilters}
                    className="mt-4 grid gap-3 sm:grid-cols-2 xl:grid-cols-[1fr_1fr_1fr_0.85fr_0.85fr_auto]"
                >
                    <div className="grid gap-1.5">
                        <Label htmlFor="history-wallet">Billetera</Label>
                        <Select value={walletId} onValueChange={setWalletId}>
                            <SelectTrigger
                                id="history-wallet"
                                className="h-11 w-full border-admin-border bg-admin-canvas"
                            >
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="all">Todas</SelectItem>
                                {wallets.map((wallet) => (
                                    <SelectItem
                                        key={wallet.id}
                                        value={wallet.id.toString()}
                                    >
                                        {wallet.name}
                                        {wallet.archived_at
                                            ? ' (archivada)'
                                            : ''}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </div>
                    <div className="grid gap-1.5">
                        <Label htmlFor="history-category">Categoría</Label>
                        <Select
                            value={categoryId}
                            onValueChange={setCategoryId}
                        >
                            <SelectTrigger
                                id="history-category"
                                className="h-11 w-full border-admin-border bg-admin-canvas"
                            >
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="all">Todas</SelectItem>
                                {categories.map((category) => (
                                    <SelectItem
                                        key={category.id}
                                        value={category.id.toString()}
                                    >
                                        {category.name}
                                        {' · '}
                                        {outflowTypeLabels[category.type]}
                                        {!category.is_active
                                            ? ' (inactiva)'
                                            : ''}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </div>
                    <div className="grid gap-1.5">
                        <Label htmlFor="history-type">Tipo</Label>
                        <Select value={type} onValueChange={setType}>
                            <SelectTrigger
                                id="history-type"
                                className="h-11 w-full border-admin-border bg-admin-canvas"
                            >
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="all">Todos</SelectItem>
                                {filterTypes.map((option) => (
                                    <SelectItem
                                        key={option.value}
                                        value={option.value}
                                    >
                                        {option.label}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </div>
                    <div className="grid gap-1.5">
                        <Label htmlFor="history-from">Desde</Label>
                        <Input
                            id="history-from"
                            type="date"
                            value={from}
                            max={to || undefined}
                            onChange={(event) => setFrom(event.target.value)}
                            className="h-11 border-admin-border bg-admin-canvas"
                        />
                    </div>
                    <div className="grid gap-1.5">
                        <Label htmlFor="history-to">Hasta</Label>
                        <Input
                            id="history-to"
                            type="date"
                            value={to}
                            min={from || undefined}
                            onChange={(event) => setTo(event.target.value)}
                            className="h-11 border-admin-border bg-admin-canvas"
                        />
                    </div>
                    <div className="grid grid-cols-2 gap-2 sm:col-span-2 xl:col-span-1 xl:self-end">
                        <Button
                            type="button"
                            variant="ghost"
                            onClick={clearFilters}
                            className="h-11"
                        >
                            Limpiar
                        </Button>
                        <Button type="submit" className="h-11">
                            Filtrar
                        </Button>
                    </div>
                </form>
            </header>

            {transactions.data.length === 0 ? (
                <p
                    role="status"
                    className="px-5 py-14 text-center text-sm text-admin-text-tertiary"
                >
                    No hay movimientos para los filtros elegidos.
                </p>
            ) : (
                <>
                    <div className="hidden lg:block">
                        <table className="w-full table-fixed text-left text-sm">
                            <thead className="border-b border-admin-border text-xs tracking-wide text-admin-text-tertiary uppercase">
                                <tr>
                                    <th
                                        scope="col"
                                        className="w-[12%] px-4 py-3 font-medium"
                                    >
                                        Fecha
                                    </th>
                                    <th
                                        scope="col"
                                        className="w-[24%] px-4 py-3 font-medium"
                                    >
                                        Concepto
                                    </th>
                                    <th
                                        scope="col"
                                        className="w-[15%] px-4 py-3 font-medium"
                                    >
                                        Billetera
                                    </th>
                                    <th
                                        scope="col"
                                        className="w-[17%] px-4 py-3 font-medium"
                                    >
                                        Tipo
                                    </th>
                                    <th
                                        scope="col"
                                        className="w-[14%] px-4 py-3 text-right font-medium"
                                    >
                                        Importe
                                    </th>
                                    <th
                                        scope="col"
                                        className="w-[18%] px-4 py-3 text-right font-medium"
                                    >
                                        Acción
                                    </th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-admin-border">
                                {transactions.data.flatMap((transaction) =>
                                    desktopRows(transaction).map((row) => (
                                        <tr key={row.key}>
                                            <td className="px-4 py-3 text-admin-text-secondary">
                                                {formatManagementDate(
                                                    transaction.effective_date,
                                                )}
                                            </td>
                                            <td className="px-4 py-3">
                                                <p className="truncate font-medium text-white">
                                                    {transactionDescription(
                                                        transaction,
                                                    )}
                                                </p>
                                                <p className="mt-0.5 truncate text-xs text-admin-text-tertiary">
                                                    {transactionSecondary(
                                                        transaction,
                                                    )}
                                                    {transactionReference(
                                                        transaction,
                                                    )}
                                                </p>
                                                {transaction.purchase_id && (
                                                    <Link
                                                        className="text-admin-accent underline"
                                                        href={`/admin/management/purchases/${transaction.purchase_id}`}
                                                    >
                                                        Ver compra
                                                    </Link>
                                                )}
                                            </td>
                                            <td className="px-4 py-3 text-admin-text-secondary">
                                                <span className="line-clamp-2">
                                                    {row.walletLabel}
                                                </span>
                                            </td>
                                            <td className="px-4 py-3 text-admin-text-secondary">
                                                <span className="block">
                                                    {transactionLabel(
                                                        transaction,
                                                    )}
                                                </span>
                                                {transaction.management_outflow && (
                                                    <span className="mt-0.5 block truncate text-xs text-admin-text-tertiary">
                                                        {
                                                            transaction
                                                                .management_outflow
                                                                .category.name
                                                        }
                                                    </span>
                                                )}
                                                {transaction.management_transfer && (
                                                    <span className="mt-0.5 block truncate text-xs text-admin-text-tertiary">
                                                        Movimiento interno
                                                    </span>
                                                )}
                                                {transaction.management_distribution && (
                                                    <span className="mt-0.5 block truncate text-xs text-admin-text-tertiary">
                                                        Distribución interna
                                                    </span>
                                                )}
                                                {transaction.management_order_payment && (
                                                    <>
                                                        <span className="mt-0.5 block truncate text-xs text-admin-text-tertiary">
                                                            Ingreso por venta
                                                        </span>
                                                        <span className="mt-0.5 block truncate text-xs text-admin-text-tertiary">
                                                            {orderPaymentDistributionStatus(
                                                                transaction,
                                                            )}
                                                        </span>
                                                    </>
                                                )}
                                                {transaction.type ===
                                                    'reversal' && (
                                                    <Badge
                                                        variant="outline"
                                                        className="mt-1 border-admin-border text-admin-text-secondary"
                                                    >
                                                        Reversión
                                                    </Badge>
                                                )}
                                                {transaction.type !==
                                                    'reversal' &&
                                                    isReversed(transaction) && (
                                                        <Badge
                                                            variant="destructive"
                                                            className="mt-1"
                                                        >
                                                            Revertido
                                                        </Badge>
                                                    )}
                                                {row.primary &&
                                                    transaction.attachments_count >
                                                        0 && (
                                                        <span className="mt-1 flex items-center gap-1 text-xs text-admin-text-tertiary">
                                                            <Paperclip
                                                                aria-hidden="true"
                                                                className="size-3.5"
                                                            />
                                                            {attachmentCountLabel(
                                                                transaction.attachments_count,
                                                            )}
                                                        </span>
                                                    )}
                                            </td>
                                            <td
                                                className={cn(
                                                    'px-4 py-3 text-right font-semibold tabular-nums',
                                                    row.amount < 0
                                                        ? 'text-destructive'
                                                        : 'text-admin-accent',
                                                )}
                                            >
                                                {row.semanticOperation
                                                    ? formatManagementMoney.format(
                                                          Math.abs(row.amount),
                                                      )
                                                    : signedMoney(row.amount)}
                                            </td>
                                            <td className="px-4 py-3 text-right">
                                                {row.primary && (
                                                    <div className="flex justify-end gap-1">
                                                        {(transaction.management_outflow ||
                                                            transaction.management_transfer ||
                                                            transaction.management_distribution ||
                                                            transaction.management_order_payment) && (
                                                            <Button
                                                                type="button"
                                                                variant="ghost"
                                                                size="icon"
                                                                aria-label={`Ver detalle del movimiento ${transaction.id}`}
                                                                onClick={() =>
                                                                    setSelectedDetail(
                                                                        transaction,
                                                                    )
                                                                }
                                                                className="size-11 text-admin-text-secondary"
                                                            >
                                                                <Eye />
                                                            </Button>
                                                        )}
                                                        {canReverse(
                                                            transaction,
                                                        ) && (
                                                            <Button
                                                                type="button"
                                                                variant="ghost"
                                                                size="icon"
                                                                aria-label={`Revertir movimiento ${transaction.id}`}
                                                                onClick={() =>
                                                                    onReverse(
                                                                        transaction,
                                                                    )
                                                                }
                                                                className="size-11 text-admin-text-secondary"
                                                            >
                                                                <RotateCcw />
                                                            </Button>
                                                        )}
                                                    </div>
                                                )}
                                            </td>
                                        </tr>
                                    )),
                                )}
                            </tbody>
                        </table>
                    </div>

                    <div className="divide-y divide-admin-border lg:hidden">
                        {transactions.data.map((transaction) => (
                            <article key={transaction.id} className="p-4">
                                <div className="flex items-start justify-between gap-3">
                                    <div className="min-w-0">
                                        <p className="font-medium text-white">
                                            {transactionDescription(
                                                transaction,
                                            )}
                                        </p>
                                        <p className="mt-1 text-xs text-admin-text-tertiary">
                                            {formatManagementDate(
                                                transaction.effective_date,
                                            )}{' '}
                                            · {transactionLabel(transaction)}
                                        </p>
                                        {transaction.management_outflow && (
                                            <p className="mt-1 text-xs text-admin-text-secondary">
                                                {
                                                    transaction
                                                        .management_outflow
                                                        .category.name
                                                }
                                                {transaction.type !==
                                                    'reversal' &&
                                                transaction.management_outflow
                                                    .counterparty
                                                    ? ` · ${transaction.management_outflow.counterparty}`
                                                    : ''}
                                            </p>
                                        )}
                                        {transaction.management_transfer && (
                                            <div className="mt-1 text-xs text-admin-text-secondary">
                                                <p>
                                                    {transferRoute(transaction)}
                                                </p>
                                                <p className="mt-1 font-semibold text-admin-accent tabular-nums">
                                                    {formatManagementMoney.format(
                                                        transaction
                                                            .management_transfer
                                                            .amount,
                                                    )}
                                                </p>
                                            </div>
                                        )}
                                        {transaction.management_distribution && (
                                            <div className="mt-1 text-xs text-admin-text-secondary">
                                                <p>
                                                    {distributionRoute(
                                                        transaction,
                                                    )}
                                                </p>
                                                <p className="mt-0.5 text-admin-text-tertiary">
                                                    {distributionRuleLabel(
                                                        transaction,
                                                    )}
                                                </p>
                                                <p className="mt-1 font-semibold text-admin-accent tabular-nums">
                                                    {formatManagementMoney.format(
                                                        transaction
                                                            .management_distribution
                                                            .amount,
                                                    )}
                                                </p>
                                            </div>
                                        )}
                                        {transaction.management_order_payment && (
                                            <div className="mt-1 text-xs text-admin-text-secondary">
                                                <p>
                                                    {transaction
                                                        .management_order_payment
                                                        .order.customer_name ??
                                                        'Sin cliente asociado'}
                                                </p>
                                                <p className="mt-0.5 text-admin-text-tertiary">
                                                    {
                                                        transaction
                                                            .management_order_payment
                                                            .destination_wallet
                                                            .name
                                                    }
                                                </p>
                                                <p className="mt-0.5 text-admin-text-tertiary">
                                                    {orderPaymentDistributionStatus(
                                                        transaction,
                                                    )}
                                                </p>
                                                <p className="mt-1 font-semibold text-admin-accent tabular-nums">
                                                    {formatManagementMoney.format(
                                                        transaction
                                                            .management_order_payment
                                                            .amount,
                                                    )}
                                                </p>
                                            </div>
                                        )}
                                        {transaction.purchase_id && (
                                            <Link
                                                className="mt-2 block text-admin-accent underline"
                                                href={`/admin/management/purchases/${transaction.purchase_id}`}
                                            >
                                                Ver compra
                                            </Link>
                                        )}
                                        {transaction.type === 'reversal' && (
                                            <Badge
                                                variant="outline"
                                                className="mt-2 border-admin-border text-admin-text-secondary"
                                            >
                                                Reversión
                                            </Badge>
                                        )}
                                        {transaction.type !== 'purchase' &&
                                            transaction.type !== 'reversal' &&
                                            isReversed(transaction) && (
                                                <Badge
                                                    variant="destructive"
                                                    className="mt-2"
                                                >
                                                    Revertido
                                                </Badge>
                                            )}
                                        {transaction.attachments_count > 0 && (
                                            <p className="mt-2 flex items-center gap-1 text-xs text-admin-text-tertiary">
                                                <Paperclip
                                                    aria-hidden="true"
                                                    className="size-3.5"
                                                />
                                                {attachmentCountLabel(
                                                    transaction.attachments_count,
                                                )}
                                            </p>
                                        )}
                                    </div>
                                    <div className="flex shrink-0 gap-1">
                                        {(transaction.management_outflow ||
                                            transaction.management_transfer ||
                                            transaction.management_distribution ||
                                            transaction.management_order_payment) && (
                                            <Button
                                                type="button"
                                                variant="ghost"
                                                size="icon"
                                                aria-label={`Ver detalle del movimiento ${transaction.id}`}
                                                onClick={() =>
                                                    setSelectedDetail(
                                                        transaction,
                                                    )
                                                }
                                                className="size-11 text-admin-text-secondary"
                                            >
                                                <Eye />
                                            </Button>
                                        )}
                                        {canReverse(transaction) && (
                                            <Button
                                                type="button"
                                                variant="ghost"
                                                size="icon"
                                                aria-label={`Revertir movimiento ${transaction.id}`}
                                                onClick={() =>
                                                    onReverse(transaction)
                                                }
                                                className="size-11 text-admin-text-secondary"
                                            >
                                                <RotateCcw />
                                            </Button>
                                        )}
                                    </div>
                                </div>
                                <ul className="mt-3 grid gap-2">
                                    {transaction.entries.map((entry) => (
                                        <li
                                            key={entry.id}
                                            className="flex items-center justify-between gap-3 rounded-lg bg-admin-surface-raised px-3 py-2.5"
                                        >
                                            <span className="min-w-0 truncate text-sm text-admin-text-secondary">
                                                {entry.wallet.name}
                                                {entry.wallet.archived_at
                                                    ? ' (archivada)'
                                                    : ''}
                                            </span>
                                            <span
                                                className={cn(
                                                    'shrink-0 text-sm font-semibold tabular-nums',
                                                    entry.amount < 0
                                                        ? 'text-destructive'
                                                        : 'text-admin-accent',
                                                )}
                                            >
                                                {signedMoney(entry.amount)}
                                            </span>
                                        </li>
                                    ))}
                                </ul>
                                <p className="mt-3 text-xs text-admin-text-tertiary">
                                    Registrado por{' '}
                                    {administratorName(transaction.created_by)}
                                    {transactionReference(transaction)}
                                </p>
                            </article>
                        ))}
                    </div>
                </>
            )}

            <nav
                aria-label="Paginación de movimientos"
                className="flex items-center justify-between border-t border-admin-border px-4 py-4 sm:px-5"
            >
                <p className="text-sm text-admin-text-tertiary">
                    {transactions.total} movimientos · página{' '}
                    {transactions.current_page} de {transactions.last_page}
                </p>
                <div className="flex gap-2">
                    <PageButton
                        href={transactions.prev_page_url}
                        label="Página anterior"
                    >
                        <ChevronLeft />
                    </PageButton>
                    <PageButton
                        href={transactions.next_page_url}
                        label="Página siguiente"
                    >
                        <ChevronRight />
                    </PageButton>
                </div>
            </nav>
            <OutflowDetailSheet
                transaction={selectedDetail}
                onOpenChange={(open) => !open && setSelectedDetail(null)}
            />
            <TransferDetailSheet
                transaction={selectedDetail}
                onOpenChange={(open) => !open && setSelectedDetail(null)}
            />
            <DistributionDetailSheet
                transaction={selectedDetail}
                onOpenChange={(open) => !open && setSelectedDetail(null)}
                onViewOrderPayment={(financialTransactionId) =>
                    viewRelatedTransaction(
                        financialTransactionId,
                        'order_payment',
                    )
                }
            />
            <OrderPaymentDetailSheet
                transaction={selectedDetail}
                onOpenChange={(open) => !open && setSelectedDetail(null)}
                onViewDistribution={(financialTransactionId) =>
                    viewRelatedTransaction(
                        financialTransactionId,
                        'distribution',
                    )
                }
            />
        </section>
    );
}

function signedMoney(amount: number) {
    const formatted = formatManagementMoney.format(Math.abs(amount));

    return `${amount >= 0 ? '+' : '−'}${formatted}`;
}

function attachmentCountLabel(count: number) {
    return `${count} ${count === 1 ? 'comprobante' : 'comprobantes'}`;
}

function desktopRows(transaction: ManagementTransaction) {
    if (transaction.management_order_payment) {
        const payment = transaction.management_order_payment;

        return [
            {
                key: `transaction-${transaction.id}`,
                walletLabel: walletName(payment.destination_wallet),
                amount: payment.amount,
                primary: true,
                semanticOperation: true,
            },
        ];
    }

    if (transaction.management_transfer) {
        return [
            {
                key: `transaction-${transaction.id}`,
                walletLabel: transferRoute(transaction),
                amount: transaction.management_transfer.amount,
                primary: true,
                semanticOperation: true,
            },
        ];
    }

    if (transaction.management_distribution) {
        return [
            {
                key: `transaction-${transaction.id}`,
                walletLabel: distributionRoute(transaction),
                amount: transaction.management_distribution.amount,
                primary: true,
                semanticOperation: true,
            },
        ];
    }

    return transaction.entries.map((entry, index) => ({
        key: `entry-${entry.id}`,
        walletLabel: `${entry.wallet.name}${entry.wallet.archived_at ? ' (archivada)' : ''}`,
        amount: entry.amount,
        primary: index === 0,
        semanticOperation: false,
    }));
}

function transferRoute(transaction: ManagementTransaction) {
    const transfer = transaction.management_transfer;

    if (!transfer) {
        return '';
    }

    const source = `${transfer.source_wallet.name}${transfer.source_wallet.archived_at ? ' (archivada)' : ''}`;
    const destination = `${transfer.destination_wallet.name}${transfer.destination_wallet.archived_at ? ' (archivada)' : ''}`;

    return `${source} → ${destination}`;
}

function distributionRoute(transaction: ManagementTransaction) {
    const distribution = transaction.management_distribution;

    if (!distribution) {
        return '';
    }

    const source = `${distribution.source_wallet.name}${distribution.source_wallet.archived_at ? ' (archivada)' : ''}`;
    const destinations = distribution.allocations
        .map((allocation) => {
            const walletName = allocation.wallet_name ?? allocation.wallet.name;

            return `${walletName}${allocation.wallet.archived_at ? ' (archivada)' : ''}`;
        })
        .join(' / ');

    return `${source} → ${destinations}`;
}

function canReverse(transaction: ManagementTransaction) {
    return (
        transaction.type !== 'purchase' &&
        transaction.type !== 'reversal' &&
        transaction.reversal_transaction_id === null &&
        transaction.management_order_payment?.distribution_status !==
            'distributed'
    );
}

function isReversed(transaction: ManagementTransaction) {
    return (
        Boolean(transaction.management_outflow?.reversed) ||
        Boolean(transaction.management_transfer?.reversed) ||
        Boolean(transaction.management_distribution?.reversed) ||
        Boolean(transaction.management_order_payment?.reversed) ||
        transaction.reversal_transaction_id !== null
    );
}

function transactionReference(transaction: ManagementTransaction) {
    if (transaction.management_order_payment) {
        if (transaction.reversal_of_id) {
            return ' · revierte el pago de pedido original';
        }

        if (transaction.reversal_transaction_id) {
            return ' · pago de pedido revertido';
        }

        return '';
    }

    if (transaction.management_transfer) {
        if (transaction.reversal_of_id) {
            return ' · revierte la transferencia original';
        }

        if (transaction.reversal_transaction_id) {
            return ' · transferencia revertida';
        }

        return '';
    }

    if (transaction.management_distribution) {
        if (transaction.reversal_of_id) {
            return ' · revierte la distribución original';
        }

        if (transaction.reversal_transaction_id) {
            return ' · distribución revertida';
        }

        return '';
    }

    if (transaction.reversal_of_id) {
        return ` · revierte #${transaction.reversal_of_id}`;
    }

    if (transaction.reversal_transaction_id) {
        return ` · revertido por #${transaction.reversal_transaction_id}`;
    }

    return '';
}

function transactionDescription(transaction: ManagementTransaction) {
    if (transaction.type === 'reversal') {
        return (
            transaction.description ||
            (transaction.management_outflow
                ? `Reversión de ${transaction.management_outflow.description}`
                : transaction.management_transfer
                  ? 'Reversión de transferencia'
                  : transaction.management_distribution
                    ? 'Reversión de distribución'
                    : transaction.management_order_payment
                      ? `Reversión de pago de pedido ${transaction.management_order_payment.order.public_id}`
                      : 'Reversión')
        );
    }

    return (
        (transaction.management_order_payment
            ? `Pago de pedido ${transaction.management_order_payment.order.public_id}`
            : null) ||
        (transaction.management_distribution?.management_order_payment
            ? `Distribución de pedido ${transaction.management_distribution.management_order_payment.order.public_id}`
            : null) ||
        transaction.management_outflow?.description ||
        transaction.management_transfer?.description ||
        transaction.management_distribution?.description ||
        transaction.description ||
        (transaction.management_distribution
            ? 'Distribución interna'
            : transaction.management_transfer
              ? 'Transferencia entre billeteras'
              : 'Sin concepto')
    );
}

function transactionSecondary(transaction: ManagementTransaction) {
    if (transaction.management_order_payment) {
        return `${transaction.management_order_payment.order.customer_name ?? 'Sin cliente asociado'} · ${orderPaymentDistributionStatus(transaction)}`;
    }

    if (transaction.management_transfer) {
        return transferRoute(transaction);
    }

    if (transaction.management_distribution) {
        return `${distributionRuleLabel(transaction)} · ${distributionRoute(transaction)}`;
    }

    if (transaction.type === 'reversal') {
        return administratorName(transaction.created_by);
    }

    return (
        transaction.management_outflow?.counterparty ??
        administratorName(transaction.created_by)
    );
}

function orderPaymentDistributionStatus(transaction: ManagementTransaction) {
    switch (transaction.management_order_payment?.distribution_status) {
        case 'distributed':
            return 'Distribuido';
        case 'reversed':
            return 'Distribución revertida';
        default:
            return 'Pendiente de distribución';
    }
}

function walletName(wallet: { name: string; archived_at: string | null }) {
    return `${wallet.name}${wallet.archived_at ? ' (archivada)' : ''}`;
}

function distributionRuleLabel(transaction: ManagementTransaction) {
    const distribution = transaction.management_distribution;

    if (!distribution) {
        return '';
    }

    const name = distribution.rule?.name ?? distribution.rule_name ?? 'Regla';
    const version =
        distribution.rule_version?.version_number ??
        distribution.rule_version_number;

    return `${name}${version ? ` v${version}` : ''}`;
}

function PageButton({
    href,
    label,
    children,
}: {
    href: string | null;
    label: string;
    children: React.ReactNode;
}) {
    return (
        <Button
            asChild={Boolean(href)}
            variant="outline"
            size="icon"
            disabled={!href}
            aria-label={label}
            className="size-11"
        >
            {href ? (
                <Link href={href}>{children}</Link>
            ) : (
                <span>{children}</span>
            )}
        </Button>
    );
}
