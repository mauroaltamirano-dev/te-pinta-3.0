import { Head, Link, usePage } from '@inertiajs/react';
import type { WalletCards } from 'lucide-react';
import {
    ArrowLeftRight,
    HandCoins,
    Plus,
    PieChart,
    ReceiptText,
    ShoppingBasket,
} from 'lucide-react';
import { useState } from 'react';
import { AdminPageShell } from '@/components/shared/admin-page-shell';
import { Button } from '@/components/ui/button';
import { adminBreadcrumbRoot } from '@/features/admin-navigation/model';
import { DistributionFormDialog } from '@/features/management/components/distribution-form-dialog';
import { DistributionRuleDialog } from '@/features/management/components/distribution-rule-dialog';
import { DistributionRulesPanel } from '@/features/management/components/distribution-rules-panel';
import { ManagementOverview } from '@/features/management/components/management-overview';
import { ManagementSectionNavigation } from '@/features/management/components/management-section-navigation';
import { MovementFormDialog } from '@/features/management/components/movement-form-dialog';
import { OutflowFormDialog } from '@/features/management/components/outflow-form-dialog';
import { ReversalDialog } from '@/features/management/components/reversal-dialog';
import { TransactionHistory } from '@/features/management/components/transaction-history';
import { TransferFormDialog } from '@/features/management/components/transfer-form-dialog';
import { WalletFormDialog } from '@/features/management/components/wallet-form-dialog';
import { WalletGrid } from '@/features/management/components/wallet-grid';
import {
    managementSectionHref,
    requestedManagementSection,
} from '@/features/management/navigation';
import type { ManagementSection } from '@/features/management/navigation';
import type {
    ManagementPageProps,
    DistributionRule,
    ManagementOutflowType,
    ManagementTransaction,
    ManagementWallet,
} from '@/features/management/types';

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
    const { url, props } = usePage();
    const activeSection = requestedManagementSection(url);
    const sectionHref = (section: ManagementSection) =>
        managementSectionHref(url, section);
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
                    ...adminBreadcrumbRoot(props.auth?.permissions ?? []),
                    { title: 'Gestión', href: '/admin/management' },
                ]}
                actions={
                    <>
                        <Button
                            asChild
                            variant={
                                activeSection === 'operations'
                                    ? 'default'
                                    : 'outline'
                            }
                        >
                            <Link
                                href={sectionHref('operations')}
                                aria-current={
                                    activeSection === 'operations'
                                        ? 'page'
                                        : undefined
                                }
                            >
                                <ReceiptText /> Registrar operación
                            </Link>
                        </Button>
                        {activeSection === 'wallets' && (
                            <Button
                                type="button"
                                onClick={() => setWalletFormOpen(true)}
                            >
                                <Plus /> Nueva billetera
                            </Button>
                        )}
                    </>
                }
                navigation={<ManagementSectionNavigation />}
            >
                <div
                    hidden={activeSection !== 'panorama'}
                    data-testid="management-summary-group"
                    className="grid min-w-0 gap-4"
                >
                    <ManagementOverview
                        summary={summary}
                        wallets={wallets}
                        filters={filters}
                        sectionHref={sectionHref}
                    />
                </div>

                <section
                    hidden={activeSection !== 'operations'}
                    data-testid="management-operations-group"
                    className="rounded-xl border border-admin-border bg-admin-surface p-4 sm:p-5"
                >
                    <header>
                        <h2 className="text-xl font-semibold text-admin-text-primary">
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
                        <h2 className="text-xl font-semibold text-admin-text-primary">
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
                    className="min-w-0"
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
