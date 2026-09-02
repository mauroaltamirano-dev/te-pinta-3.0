import { Head, Link } from '@inertiajs/react';
import {
    ArrowDown,
    ArrowLeftRight,
    ArrowUp,
    HandCoins,
    Plus,
    PieChart,
    ReceiptText,
    ShoppingBasket,
    ShieldCheck,
    WalletCards,
} from 'lucide-react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
import { DistributionFormDialog } from '@/features/management/components/distribution-form-dialog';
import { DistributionRuleDialog } from '@/features/management/components/distribution-rule-dialog';
import { DistributionRulesPanel } from '@/features/management/components/distribution-rules-panel';
import { MovementFormDialog } from '@/features/management/components/movement-form-dialog';
import { OutflowFormDialog } from '@/features/management/components/outflow-form-dialog';
import { ReversalDialog } from '@/features/management/components/reversal-dialog';
import { TransactionHistory } from '@/features/management/components/transaction-history';
import { TransferFormDialog } from '@/features/management/components/transfer-form-dialog';
import { WalletFormDialog } from '@/features/management/components/wallet-form-dialog';
import { WalletGrid } from '@/features/management/components/wallet-grid';
import { formatManagementMoney } from '@/features/management/presentation';
import type {
    ManagementPageProps,
    DistributionRule,
    ManagementOutflowType,
    ManagementTransaction,
    ManagementWallet,
} from '@/features/management/types';

export default function Management({
    wallets,
    transactions,
    summary,
    filters,
    movement_types: movementTypes,
    outflow_types: outflowTypes,
    outflow_categories: outflowCategories,
    distribution_rules: distributionRules,
}: ManagementPageProps) {
    const [walletFormOpen, setWalletFormOpen] = useState(false);
    const [editingWallet, setEditingWallet] = useState<ManagementWallet | null>(
        null,
    );
    const [movementFormOpen, setMovementFormOpen] = useState(false);
    const [transferFormOpen, setTransferFormOpen] = useState(false);
    const [distributionFormOpen, setDistributionFormOpen] = useState(false);
    const [ruleFormOpen, setRuleFormOpen] = useState(false);
    const [versioningRule, setVersioningRule] =
        useState<DistributionRule | null>(null);
    const [outflowType, setOutflowType] =
        useState<ManagementOutflowType | null>(null);
    const [reversing, setReversing] = useState<ManagementTransaction | null>(
        null,
    );
    const activeWallets = wallets.filter((wallet) => !wallet.archived_at);
    const closeWalletForm = (open: boolean) => {
        setWalletFormOpen(open);

        if (!open) {
            setEditingWallet(null);
        }
    };
    const editWallet = (wallet: ManagementWallet) => {
        setEditingWallet(wallet);
        setWalletFormOpen(true);
    };
    const canCreateOutflow = (type: ManagementOutflowType) =>
        activeWallets.length > 0 &&
        outflowCategories.some(
            (category) => category.is_active && category.type === type,
        );

    return (
        <>
            <Head title="Gestión" />
            <section className="grid gap-6 font-orders">
                <header className="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                    <div>
                        <p className="text-sm font-medium text-admin-accent">
                            Operación financiera
                        </p>
                        <h1 className="mt-1 text-3xl font-semibold tracking-tight text-white">
                            Gestión
                        </h1>
                        <p className="mt-1 max-w-2xl text-sm text-admin-text-tertiary">
                            Registrá operaciones y consultá saldos derivados del
                            historial financiero. Los pedidos confirmados como
                            pagados ingresan en su billetera receptora.
                        </p>
                    </div>
                    <div className="grid grid-cols-2 gap-2 sm:flex">
                        <Button
                            asChild
                            variant="outline"
                            className="h-11 rounded-lg px-4"
                        >
                            <Link href="/admin/management/audit">
                                <ShieldCheck /> Auditoría
                            </Link>
                        </Button>
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => setWalletFormOpen(true)}
                            className="h-11 rounded-lg px-4"
                        >
                            <Plus /> Nueva billetera
                        </Button>
                    </div>
                </header>

                <section
                    aria-label="Resumen financiero"
                    className="grid gap-3 sm:grid-cols-2 xl:grid-cols-5"
                >
                    <SummaryCard
                        label="Saldo total"
                        value={summary.total_balance}
                        description="Billeteras activas"
                        icon={WalletCards}
                    />
                    <SummaryCard
                        label="Ingresos del período"
                        value={summary.period_income}
                        description="Movimientos registrados"
                        icon={ArrowUp}
                    />
                    <SummaryCard
                        label="Egresos operativos"
                        value={summary.period_operating_outflow}
                        description="Compras y gastos del período"
                        icon={ArrowDown}
                        negative
                    />
                    <SummaryCard
                        label="Retiros personales"
                        value={summary.period_personal_withdrawal}
                        description="Separados de la operación"
                        icon={HandCoins}
                        negative
                    />
                    <SummaryCard
                        label="Distribuciones internas"
                        value={summary.period_distribution}
                        description="Asignaciones del período"
                        icon={PieChart}
                    />
                </section>

                <section className="rounded-xl border border-admin-border bg-admin-surface p-4 sm:p-5">
                    <header>
                        <h2 className="text-xl font-semibold text-white">
                            Registrar operación
                        </h2>
                        <p className="mt-1 text-sm text-admin-text-tertiary">
                            Elegí qué ocurrió. El backend determina y registra
                            el impacto financiero.
                        </p>
                    </header>
                    <div className="mt-4 grid gap-2 sm:grid-cols-2 xl:grid-cols-5">
                        <OperationButton
                            label="Registrar compra"
                            icon={ShoppingBasket}
                            disabled={!canCreateOutflow('purchase')}
                            onClick={() => setOutflowType('purchase')}
                        />
                        <OperationButton
                            label="Registrar gasto"
                            icon={ReceiptText}
                            disabled={!canCreateOutflow('expense')}
                            onClick={() => setOutflowType('expense')}
                        />
                        <OperationButton
                            label="Retiro personal"
                            icon={HandCoins}
                            disabled={!canCreateOutflow('personal_withdrawal')}
                            onClick={() =>
                                setOutflowType('personal_withdrawal')
                            }
                        />
                        <OperationButton
                            label="Transferir"
                            icon={ArrowLeftRight}
                            disabled={activeWallets.length < 2}
                            disabledTitle="Requiere dos billeteras activas"
                            onClick={() => setTransferFormOpen(true)}
                        />
                        <OperationButton
                            label="Distribuir dinero"
                            icon={PieChart}
                            disabled={
                                !distributionRules.some((rule) => {
                                    const version = rule.versions[0];

                                    return (
                                        (rule.active ??
                                            rule.is_active ??
                                            false) &&
                                        version &&
                                        activeWallets.some(
                                            (wallet) =>
                                                !version.allocations.some(
                                                    (allocation) =>
                                                        allocation.wallet.id ===
                                                        wallet.id,
                                                ),
                                        )
                                    );
                                })
                            }
                            disabledTitle="Requiere una regla activa y una billetera origen distinta"
                            onClick={() => setDistributionFormOpen(true)}
                        />
                    </div>
                    <div className="mt-3 border-t border-admin-border pt-3">
                        <Button
                            type="button"
                            variant="ghost"
                            onClick={() => setMovementFormOpen(true)}
                            disabled={activeWallets.length === 0}
                            className="h-11 justify-start rounded-lg px-3 text-admin-text-secondary"
                            title={
                                activeWallets.length === 0
                                    ? 'Creá una billetera activa primero'
                                    : 'Aperturas, ajustes y movimientos excepcionales'
                            }
                        >
                            <Plus /> Movimiento manual / Más acciones
                        </Button>
                    </div>
                </section>

                <section className="rounded-xl border border-admin-border bg-admin-surface p-4 sm:p-5">
                    <header className="mb-4">
                        <h2 className="text-xl font-semibold text-white">
                            Billeteras
                        </h2>
                        <p className="mt-1 text-sm text-admin-text-tertiary">
                            Cada saldo es la suma de sus entradas del ledger.
                        </p>
                    </header>
                    <WalletGrid wallets={wallets} onEdit={editWallet} />
                </section>

                <DistributionRulesPanel
                    rules={distributionRules}
                    onCreate={() => setRuleFormOpen(true)}
                    onCreateVersion={setVersioningRule}
                />

                <TransactionHistory
                    transactions={transactions}
                    wallets={wallets}
                    filters={filters}
                    movementTypes={movementTypes}
                    outflowTypes={outflowTypes}
                    categories={outflowCategories}
                    onReverse={setReversing}
                />
            </section>

            {walletFormOpen && (
                <WalletFormDialog
                    open={walletFormOpen}
                    onOpenChange={closeWalletForm}
                    wallet={editingWallet ?? undefined}
                />
            )}
            {movementFormOpen && (
                <MovementFormDialog
                    open={movementFormOpen}
                    onOpenChange={setMovementFormOpen}
                    wallets={activeWallets}
                    movementTypes={movementTypes}
                />
            )}
            {outflowType && (
                <OutflowFormDialog
                    key={outflowType}
                    open
                    onOpenChange={(open) => !open && setOutflowType(null)}
                    initialType={outflowType}
                    wallets={activeWallets}
                    outflowTypes={outflowTypes}
                    categories={outflowCategories}
                />
            )}
            {transferFormOpen && (
                <TransferFormDialog
                    open={transferFormOpen}
                    onOpenChange={setTransferFormOpen}
                    wallets={activeWallets}
                />
            )}
            {distributionFormOpen && (
                <DistributionFormDialog
                    open={distributionFormOpen}
                    onOpenChange={setDistributionFormOpen}
                    rules={distributionRules}
                    wallets={activeWallets}
                />
            )}
            {(ruleFormOpen || versioningRule) && (
                <DistributionRuleDialog
                    key={versioningRule?.id ?? 'new'}
                    open
                    onOpenChange={(open) => {
                        if (!open) {
                            setRuleFormOpen(false);
                            setVersioningRule(null);
                        }
                    }}
                    wallets={activeWallets}
                    rule={versioningRule ?? undefined}
                />
            )}
            {reversing && (
                <ReversalDialog
                    transaction={reversing}
                    onOpenChange={(open) => !open && setReversing(null)}
                />
            )}
        </>
    );
}

function OperationButton({
    label,
    icon: Icon,
    disabled,
    disabledTitle,
    onClick,
}: {
    label: string;
    icon: typeof WalletCards;
    disabled: boolean;
    disabledTitle?: string;
    onClick: () => void;
}) {
    return (
        <Button
            type="button"
            onClick={onClick}
            disabled={disabled}
            className="h-11 justify-start rounded-lg px-4"
            title={
                disabled
                    ? (disabledTitle ??
                      'Requiere billetera y categoría activas')
                    : undefined
            }
        >
            <Icon /> {label}
        </Button>
    );
}

function SummaryCard({
    label,
    value,
    description,
    icon: Icon,
    negative = false,
}: {
    label: string;
    value: number;
    description: string;
    icon: typeof WalletCards;
    negative?: boolean;
}) {
    return (
        <article className="rounded-xl border border-admin-border bg-admin-surface p-4 sm:p-5">
            <div className="flex items-start justify-between gap-3">
                <div>
                    <p className="text-sm font-medium text-admin-text-secondary">
                        {label}
                    </p>
                    <p
                        className={`mt-2 text-2xl font-semibold tabular-nums ${negative ? 'text-destructive' : 'text-white'}`}
                    >
                        {formatManagementMoney.format(value)}
                    </p>
                    <p className="mt-1 text-xs text-admin-text-tertiary">
                        {description}
                    </p>
                </div>
                <span className="flex size-10 shrink-0 items-center justify-center rounded-lg bg-admin-action text-admin-accent">
                    <Icon className="size-5" aria-hidden="true" />
                </span>
            </div>
        </article>
    );
}
