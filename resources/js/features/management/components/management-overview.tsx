import { Link } from '@inertiajs/react';
import { AdminEmptyState } from '@/components/shared/admin-empty-state';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader } from '@/components/ui/card';
import { formatMoney } from '@/lib/format';
import type {
    ManagementFilters,
    ManagementMetrics,
    ManagementWallet,
} from '../types';
import { ManagementPeriodFilter } from './management-period-filter';

export function ManagementOverview({
    summary,
    wallets,
    filters,
    sectionHref,
}: {
    summary: ManagementMetrics;
    wallets: ManagementWallet[];
    filters: ManagementFilters;
    sectionHref: (section: string) => string;
}) {
    // Preserve the existing presentation aggregate; financial formulas remain backend-owned.
    const expenses = summary.period_expenses + (summary.period_expense ?? 0);
    const zeroNet = [
        summary.period_income,
        summary.period_purchases,
        summary.period_purchase_shipping,
        summary.period_expenses,
        summary.period_expense ?? 0,
        summary.period_personal_withdrawal,
        summary.period_distribution,
        summary.period_other_movements,
        summary.period_balance_change,
        summary.reconciliation_difference,
    ].every((value) => value === 0);
    const kpis = [
        {
            key: 'balance',
            label: 'Saldo actual',
            value: summary.total_balance,
            hint: 'Todas las billeteras, incluidas las archivadas. No depende del período seleccionado.',
        },
        {
            key: 'income',
            label: 'Ingresos del período',
            value: summary.period_income,
            hint: 'Ingresos registrados; las transferencias internas no se cuentan como ingresos.',
        },
        {
            key: 'result',
            label: 'Resultado operativo',
            value: summary.period_operating_result,
            hint: 'Ingresos − compras − gastos. Los retiros personales se muestran por separado.',
        },
    ];
    const outflows = [
        {
            label: 'Compras',
            value: summary.period_purchases,
            hint: 'Compras del período, incluido el envío.',
        },
        {
            label: 'Gastos',
            value: expenses,
            hint: `Gastos operativos: ${formatMoney(summary.period_expenses)} · Egresos manuales: ${formatMoney(summary.period_expense ?? 0)}.`,
        },
        {
            label: 'Retiros personales',
            value: summary.period_personal_withdrawal,
            hint: 'No forman parte del resultado operativo.',
        },
        {
            label: 'Envío de compras',
            value: summary.period_purchase_shipping,
            hint: 'Ya incluido en Compras; no se suma nuevamente.',
        },
    ];
    const balances = [
        {
            label: 'Saldo inicial',
            value: summary.period_opening_balance,
            hint: 'Antes del período seleccionado.',
        },
        {
            label: 'Saldo final',
            value: summary.period_closing_balance,
            hint: 'Al cierre del período seleccionado.',
        },
        {
            label: 'Distribuciones internas',
            value: summary.period_distribution,
            hint: 'Asignaciones netas entre billeteras; no son ingresos ni gastos operativos.',
        },
        {
            label: 'Otros movimientos',
            value: summary.period_other_movements,
            hint: 'Aperturas y ajustes financieros del período.',
        },
    ];

    return (
        <section
            aria-label="Panorama financiero"
            className="admin-ui grid min-w-0 gap-6"
        >
            <Card className="admin-ui admin-panel">
                <CardContent>
                    <ManagementPeriodFilter
                        key={`${filters.period}:${filters.from}:${filters.to}`}
                        filters={filters}
                    />
                </CardContent>
            </Card>
            <section
                aria-label="Indicadores principales"
                className="grid min-w-0 gap-4 lg:grid-cols-3"
            >
                {kpis.map((kpi) => (
                    <Card
                        key={kpi.key}
                        role="group"
                        aria-labelledby={`overview-${kpi.key}`}
                        className="admin-ui admin-panel"
                    >
                        <CardContent className="grid min-w-0 content-start gap-2">
                            <h2
                                id={`overview-${kpi.key}`}
                                className="admin-section-title text-admin-text-secondary"
                            >
                                {kpi.label}
                            </h2>
                            <p className="text-3xl font-semibold break-words text-admin-text-primary tabular-nums">
                                {formatMoney(kpi.value)}
                            </p>
                            <p className="text-sm text-admin-text-secondary">
                                {kpi.hint}
                            </p>
                            {kpi.key === 'result' && kpi.value < 0 && (
                                <p className="text-sm font-medium">
                                    Resultado operativo negativo
                                </p>
                            )}
                        </CardContent>
                    </Card>
                ))}
            </section>
            {zeroNet && (
                <AdminEmptyState
                    title="Sin actividad neta en este período"
                    description="Los valores netos del período son cero. Movimientos permite consultar operaciones y reversiones, incluso cuando se compensan entre sí."
                    action={
                        <Button asChild>
                            <Link href={sectionHref('operations')}>
                                Registrar operación
                            </Link>
                        </Button>
                    }
                    secondaryAction={
                        <Button asChild variant="ghost">
                            <Link href={sectionHref('movements')}>
                                Ver movimientos
                            </Link>
                        </Button>
                    }
                />
            )}
            <div className="grid min-w-0 gap-6 lg:grid-cols-2">
                {[
                    {
                        id: 'outflows',
                        title: 'Egresos del período',
                        rows: outflows,
                    },
                    {
                        id: 'balances',
                        title: 'Saldos del período y movimientos internos',
                        rows: balances,
                    },
                ].map((section) => (
                    <Card
                        key={section.id}
                        role="region"
                        aria-labelledby={`overview-${section.id}`}
                        className="admin-ui admin-panel min-w-0"
                    >
                        <CardHeader>
                            <h2
                                id={`overview-${section.id}`}
                                className="admin-section-title"
                            >
                                {section.title}
                            </h2>
                        </CardHeader>
                        <CardContent>
                            <dl className="grid gap-4">
                                {section.rows.map((row) => (
                                    <div
                                        key={row.label}
                                        className="flex min-w-0 flex-wrap items-baseline justify-between gap-x-4 gap-y-1 border-b border-admin-border pb-4 last:border-0 last:pb-0"
                                    >
                                        <dt className="font-medium">
                                            {row.label}
                                        </dt>
                                        <dd className="font-semibold tabular-nums">
                                            {formatMoney(row.value)}
                                        </dd>
                                        <dd className="w-full text-sm text-admin-text-secondary">
                                            {row.hint}
                                        </dd>
                                    </div>
                                ))}
                            </dl>
                        </CardContent>
                    </Card>
                ))}
            </div>
            <Card
                role="region"
                aria-labelledby="overview-wallets"
                className="admin-ui admin-panel min-w-0"
            >
                <CardHeader className="gap-3">
                    <div className="flex flex-wrap items-center justify-between gap-3">
                        <h2
                            id="overview-wallets"
                            className="admin-section-title"
                        >
                            Saldos actuales por billetera
                        </h2>
                        <Button asChild variant="ghost">
                            <Link href={sectionHref('wallets')}>
                                Ver billeteras
                            </Link>
                        </Button>
                    </div>
                    <p className="text-sm text-admin-text-secondary">
                        Saldos registrados en el ledger, no saldos del período.
                        Las transferencias internas no cambian el total del
                        negocio.
                    </p>
                </CardHeader>
                <CardContent>
                    {wallets.length > 0 ? (
                        <ul className="grid gap-4">
                            {wallets.map((wallet) => (
                                <li
                                    key={wallet.id}
                                    className="flex min-w-0 flex-wrap items-baseline justify-between gap-x-4 gap-y-1 border-b border-admin-border pb-4 last:border-0 last:pb-0"
                                >
                                    <div className="min-w-0">
                                        <p className="font-medium break-words">
                                            {wallet.name}
                                        </p>
                                        <p className="text-sm text-admin-text-secondary">
                                            {wallet.archived_at
                                                ? 'Archivada · conserva su historial'
                                                : 'Activa'}
                                        </p>
                                    </div>
                                    <p className="font-semibold tabular-nums">
                                        {formatMoney(wallet.balance)}
                                    </p>
                                </li>
                            ))}
                        </ul>
                    ) : (
                        <AdminEmptyState
                            title="Sin billeteras configuradas"
                            description="Se necesita una billetera activa para registrar operaciones financieras."
                            action={
                                <Button asChild variant="secondary">
                                    <Link href={sectionHref('wallets')}>
                                        Configurar billeteras
                                    </Link>
                                </Button>
                            }
                        />
                    )}
                </CardContent>
            </Card>
            <Card
                role="region"
                aria-labelledby="overview-reconciliation"
                className="admin-ui admin-panel"
            >
                <CardHeader>
                    <h2
                        id="overview-reconciliation"
                        className="admin-section-title"
                    >
                        Conciliación del período
                    </h2>
                </CardHeader>
                <CardContent className="grid gap-3">
                    <p
                        role={
                            summary.reconciliation_difference !== 0
                                ? 'alert'
                                : undefined
                        }
                        className="font-medium"
                    >
                        {summary.reconciliation_difference === 0
                            ? 'Sin diferencia de conciliación'
                            : 'Hay una diferencia de conciliación'}
                        : {formatMoney(summary.reconciliation_difference)}.
                    </p>
                    <p className="text-sm text-admin-text-secondary">
                        Compara el cambio de saldo con el resultado operativo,
                        los retiros personales y los otros movimientos. La
                        auditoría conserva el detalle de las advertencias.
                    </p>
                    <div>
                        <Button asChild variant="outline">
                            <Link
                                href={`/admin/management/audit?period=custom&from=${filters.from}&to=${filters.to}`}
                            >
                                Ver auditoría del período
                            </Link>
                        </Button>
                    </div>
                </CardContent>
            </Card>
        </section>
    );
}
