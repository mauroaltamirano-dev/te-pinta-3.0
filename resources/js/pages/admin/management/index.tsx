import { Head, Link, usePage } from '@inertiajs/react';
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
import { AdminPageShell } from '@/components/shared/admin-page-shell';
import { AdminSectionNavigation } from '@/components/shared/admin-section-navigation';
import { Button } from '@/components/ui/button';
import { DistributionFormDialog } from '@/features/management/components/distribution-form-dialog';
import { DistributionRuleDialog } from '@/features/management/components/distribution-rule-dialog';
import { DistributionRulesPanel } from '@/features/management/components/distribution-rules-panel';
import { ManagementPeriodFilter } from '@/features/management/components/management-period-filter';
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

const sections = [
    { id: 'panorama', label: 'Panorama' },
    { id: 'operations', label: 'Registrar operación' },
    { id: 'wallets', label: 'Billeteras' },
    { id: 'movements', label: 'Movimientos' },
] as const;

function requestedSection(url: string) {
    const query = new URL(url, 'http://localhost').searchParams;
    const section = query.get('section');

    if (section && sections.some((candidate) => candidate.id === section)) {
        return section;
    }

    return [
        'transaction_id',
        'type',
        'wallet_id',
        'category_id',
        'page',
        'history_period',
        'history_from',
        'history_to',
    ].some((key) => query.has(key))
        ? 'movements'
        : 'panorama';
}

export default function Management({
    business_today: businessToday,
    wallets,
    transactions,
    summary,
    filters,
    movement_types: movementTypes,
    outflow_types: outflowTypes,
    outflow_categories: outflowCategories,
    distribution_rules: distributionRules,
}: ManagementPageProps) {
    const { url } = usePage();
    const activeSection = requestedSection(url);
    const sectionHref = (section: string) => {
        const query = new URL(url, 'http://localhost').searchParams;
        query.set('section', section);

        return `/admin/management?${query}`;
    };
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
            <AdminPageShell
                title="Gestión"
                description="Registrá operaciones y consultá saldos derivados del historial financiero. Los pedidos confirmados como pagados ingresan en su billetera receptora."
                breadcrumbs={[
                    { title: 'Administración', href: '/admin' },
                    { title: 'Gestión', href: '/admin/management' },
                ]}
                actions={
                    <>
                        <nav
                            aria-label="Áreas relacionadas de Gestión"
                            className="admin-page-actions"
                        >
                            <Button asChild variant="ghost">
                                <Link href="/admin/management/purchases">
                                    Compras
                                </Link>
                            </Button>
                            <Button asChild variant="ghost">
                                <Link href="/admin/management/audit">
                                    <ShieldCheck /> Auditoría
                                </Link>
                            </Button>
                        </nav>
                        <Button
                            type="button"
                            onClick={() => setWalletFormOpen(true)}
                        >
                            <Plus /> Nueva billetera
                        </Button>
                    </>
                }
                navigation={
                    <AdminSectionNavigation
                        label="Secciones de Gestión"
                        currentUrl={sectionHref(activeSection)}
                        items={sections.map((section) => ({
                            label: section.label,
                            href: sectionHref(section.id),
                        }))}
                    />
                }
            >
                <div
                    hidden={activeSection !== 'panorama'}
                    data-testid="management-summary-group"
                    className="grid min-w-0 gap-4"
                >
                    <ManagementPeriodFilter
                        key={`${filters.period}:${filters.from}:${filters.to}`}
                        filters={filters}
                    />
                    <section
                        aria-label="Resumen financiero"
                        className="grid gap-3 sm:grid-cols-2 xl:grid-cols-5"
                    >
                        <SummaryCard
                            label="Saldo total"
                            value={summary.total_balance}
                            description="Todas las billeteras, incluidas las archivadas"
                            icon={WalletCards}
                        />
                        <SummaryCard
                            label="Ingresos del período"
                            value={summary.period_income}
                            description="Movimientos registrados"
                            icon={ArrowUp}
                        />
                        <SummaryCard
                            label="Compras"
                            value={summary.period_purchases}
                            description="Rich + legacy, incluye envío"
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
                        <SummaryCard
                            label="Saldo inicial"
                            value={summary.period_opening_balance}
                            description="Antes del período"
                            icon={WalletCards}
                        />
                        <SummaryCard
                            label="Saldo final"
                            value={summary.period_closing_balance}
                            description="Al cierre del período"
                            icon={WalletCards}
                        />
                        <SummaryCard
                            label="Gastos"
                            value={
                                summary.period_expenses +
                                (summary.period_expense ?? 0)
                            }
                            description="Gastos operativos y egresos manuales"
                            icon={ReceiptText}
                        />
                        <SummaryCard
                            label="Resultado operativo"
                            value={summary.period_operating_result}
                            description="Ingresos − compras − gastos; sin retiros personales"
                            icon={ArrowUp}
                        />
                        <SummaryCard
                            label="Envío de compras"
                            value={summary.period_purchase_shipping}
                            description="Incluido en compras, no se suma nuevamente"
                            icon={ShoppingBasket}
                        />
                        <SummaryCard
                            label="Otros movimientos"
                            value={summary.period_other_movements}
                            description="Aperturas y ajustes financieros del período"
                            icon={WalletCards}
                        />
                    </section>

                    <p
                        role={
                            summary.reconciliation_difference !== 0
                                ? 'alert'
                                : undefined
                        }
                        className="text-sm text-admin-text-secondary"
                    >
                        Diferencia de reconciliación:{' '}
                        {formatManagementMoney.format(
                            summary.reconciliation_difference,
                        )}
                        .{' '}
                        <Link
                            className="underline"
                            href={`/admin/management/audit?period=custom&from=${filters.from}&to=${filters.to}`}
                        >
                            Ver auditoría del período
                        </Link>
                    </p>
                </div>

                <section
                    hidden={activeSection !== 'operations'}
                    data-testid="management-operations-group"
                    className="rounded-xl border border-admin-border bg-admin-surface p-4 sm:p-5"
                >
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
                        <Button asChild>
                            <Link href="/admin/management/purchases/create">
                                <ShoppingBasket /> Registrar compra
                            </Link>
                        </Button>
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

                <section
                    hidden={activeSection !== 'wallets'}
                    data-testid="management-wallets-group"
                    className="rounded-xl border border-admin-border bg-admin-surface p-4 sm:p-5"
                >
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

                <div hidden={activeSection !== 'operations'}>
                    <DistributionRulesPanel
                        rules={distributionRules}
                        onCreate={() => setRuleFormOpen(true)}
                        onCreateVersion={setVersioningRule}
                    />
                </div>

                <div
                    hidden={activeSection !== 'movements'}
                    data-testid="management-movements-group"
                >
                    <TransactionHistory
                        key={`${filters.history_from}:${filters.history_to}:${filters.wallet_id}:${filters.type}:${filters.category_id}`}
                        transactions={transactions}
                        wallets={wallets}
                        filters={filters}
                        movementTypes={movementTypes}
                        outflowTypes={outflowTypes}
                        categories={outflowCategories}
                        onReverse={setReversing}
                    />
                </div>
            </AdminPageShell>

            {walletFormOpen && (
                <WalletFormDialog
                    open={walletFormOpen}
                    onOpenChange={closeWalletForm}
                    wallet={editingWallet ?? undefined}
                />
            )}
            {movementFormOpen && (
                <MovementFormDialog
                    businessToday={businessToday}
                    open={movementFormOpen}
                    onOpenChange={setMovementFormOpen}
                    wallets={activeWallets}
                    movementTypes={movementTypes}
                />
            )}
            {outflowType && (
                <OutflowFormDialog
                    businessToday={businessToday}
                    key={outflowType}
                    open
                    onOpenChange={(open) => !open && setOutflowType(null)}
                    initialType={outflowType}
                    wallets={activeWallets}
                    outflowTypes={outflowTypes.filter(
                        (type) => type.value !== 'purchase',
                    )}
                    categories={outflowCategories}
                />
            )}
            {transferFormOpen && (
                <TransferFormDialog
                    businessToday={businessToday}
                    open={transferFormOpen}
                    onOpenChange={setTransferFormOpen}
                    wallets={activeWallets}
                />
            )}
            {distributionFormOpen && (
                <DistributionFormDialog
                    businessToday={businessToday}
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
                    businessToday={businessToday}
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
