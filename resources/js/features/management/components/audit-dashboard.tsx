import { Link, router } from '@inertiajs/react';
import {
    AlertTriangle,
    CheckCircle2,
    ChevronLeft,
    ChevronRight,
    CircleAlert,
    Clock3,
} from 'lucide-react';
import { useState } from 'react';
import type { FormEvent } from 'react';
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
import { formatMoney } from '@/lib/format';
import { cn } from '@/lib/utils';
import { formatManagementDate } from '../presentation';
import type {
    ManagementAuditData,
    ManagementAuditFinding,
    ManagementAuditSeverity,
} from '../types';

export function AuditDashboard({ audit }: { audit: ManagementAuditData }) {
    return (
        <div className="grid gap-6">
            <AuditSummary summary={audit.summary} />
            <AuditFindings audit={audit} />
            <WalletBalanceAudit wallets={audit.wallet_balances} />
        </div>
    );
}

function AuditSummary({
    summary,
}: {
    summary: ManagementAuditData['summary'];
}) {
    const cards = [
        {
            label: 'OK',
            value: summary.ok,
            description: 'Sin findings detectados',
            icon: CheckCircle2,
            className: 'text-admin-accent-text',
            href: '/admin/management/audit?status=ok',
        },
        {
            label: 'Pendientes',
            value: summary.pending,
            description: 'Situaciones operativas válidas',
            icon: Clock3,
            className: 'text-warning',
            href: '/admin/management/audit?type=pending_distribution',
        },
        {
            label: 'Warnings',
            value: summary.warning,
            description: 'Requieren atención',
            icon: AlertTriangle,
            className: 'text-warning',
            href: '/admin/management/audit?severity=warning',
        },
        {
            label: 'Críticos',
            value: summary.critical,
            description: 'Requieren revisión prioritaria',
            icon: CircleAlert,
            className: 'text-destructive',
            href: '/admin/management/audit?severity=critical',
        },
    ];

    return (
        <section
            aria-label="Resumen de auditoría"
            className="grid gap-3 sm:grid-cols-2 xl:grid-cols-4"
        >
            {cards.map(
                ({
                    label,
                    value,
                    description,
                    icon: Icon,
                    className,
                    href,
                }) => (
                    <Link
                        key={label}
                        href={href}
                        className="rounded-xl border border-admin-border bg-admin-surface p-4 transition-colors hover:border-admin-accent/40 focus-visible:ring-2 focus-visible:ring-admin-accent focus-visible:outline-none sm:p-5"
                    >
                        <div className="flex items-start justify-between gap-3">
                            <div>
                                <p className="text-sm font-medium text-admin-text-secondary">
                                    {label}
                                </p>
                                <p
                                    className={cn(
                                        'mt-2 text-3xl font-semibold tabular-nums',
                                        className,
                                    )}
                                >
                                    {value}
                                </p>
                                <p className="mt-1 text-xs text-admin-text-tertiary">
                                    {description}
                                </p>
                            </div>
                            <span
                                className={cn(
                                    'flex size-10 shrink-0 items-center justify-center rounded-lg bg-admin-action',
                                    className,
                                )}
                            >
                                <Icon className="size-5" aria-hidden="true" />
                            </span>
                        </div>
                    </Link>
                ),
            )}
        </section>
    );
}

function AuditFindings({ audit }: { audit: ManagementAuditData }) {
    const { findings, filters, options } = audit;
    const [period, setPeriod] = useState(filters.period ?? '30_days');
    const [from, setFrom] = useState(filters.from ?? '');
    const [to, setTo] = useState(filters.to ?? '');
    const [walletId, setWalletId] = useState(
        filters.wallet_id?.toString() ?? 'all',
    );
    const [type, setType] = useState(filters.type ?? 'all');
    const [severity, setSeverity] = useState(filters.severity ?? 'all');
    const [status, setStatus] = useState(filters.status ?? 'all');
    const applyFilters = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        router.get(
            '/admin/management/audit',
            {
                period,
                ...(period === 'custom' && from ? { from } : {}),
                ...(period === 'custom' && to ? { to } : {}),
                ...(walletId !== 'all' ? { wallet_id: walletId } : {}),
                ...(type !== 'all' ? { type } : {}),
                ...(severity !== 'all' ? { severity } : {}),
                ...(status !== 'all' ? { status } : {}),
            },
            { preserveState: true, replace: true, preserveScroll: true },
        );
    };
    const clearFilters = () => {
        setPeriod('30_days');
        setFrom('');
        setTo('');
        setWalletId('all');
        setType('all');
        setSeverity('all');
        setStatus('all');
        router.get(
            '/admin/management/audit',
            {},
            { preserveState: true, replace: true, preserveScroll: true },
        );
    };

    return (
        <section className="overflow-hidden rounded-xl border border-admin-border bg-admin-surface">
            <header className="border-b border-admin-border p-4 sm:p-5">
                <h2 className="text-xl font-semibold text-admin-text-primary">
                    Findings
                </h2>
                <p className="mt-1 text-sm text-admin-text-tertiary">
                    Situaciones derivadas del ledger y de sus entidades
                    semánticas. La auditoría no modifica movimientos.
                </p>
                <form
                    onSubmit={applyFilters}
                    className="mt-4 grid gap-3 sm:grid-cols-2 xl:grid-cols-4"
                >
                    <AuditSelect
                        id="audit-period"
                        label="Período"
                        value={period}
                        onValueChange={(value) =>
                            setPeriod(
                                value as ManagementAuditData['filters']['period'],
                            )
                        }
                        options={[
                            { value: 'today', label: 'Hoy' },
                            { value: '7_days', label: 'Últimos 7 días' },
                            { value: '30_days', label: 'Últimos 30 días' },
                            { value: 'custom', label: 'Personalizado' },
                        ]}
                    />
                    <AuditSelect
                        id="audit-wallet"
                        label="Billetera"
                        value={walletId}
                        onValueChange={setWalletId}
                        options={options.wallets.map((wallet) => ({
                            value: wallet.value.toString(),
                            label: wallet.label,
                        }))}
                    />
                    <AuditSelect
                        id="audit-type"
                        label="Tipo"
                        value={type}
                        onValueChange={setType}
                        options={options.types}
                    />
                    <AuditSelect
                        id="audit-severity"
                        label="Severidad"
                        value={severity}
                        onValueChange={setSeverity}
                        options={options.severities}
                    />
                    <AuditSelect
                        id="audit-status"
                        label="Estado"
                        value={status}
                        onValueChange={setStatus}
                        options={options.statuses}
                    />
                    {period === 'custom' && (
                        <>
                            <div className="grid gap-1.5">
                                <Label htmlFor="audit-from">Desde</Label>
                                <Input
                                    id="audit-from"
                                    type="date"
                                    value={from}
                                    max={to || undefined}
                                    onChange={(event) =>
                                        setFrom(event.target.value)
                                    }
                                    className="h-11 border-admin-border bg-admin-canvas"
                                />
                            </div>
                            <div className="grid gap-1.5">
                                <Label htmlFor="audit-to">Hasta</Label>
                                <Input
                                    id="audit-to"
                                    type="date"
                                    value={to}
                                    min={from || undefined}
                                    onChange={(event) =>
                                        setTo(event.target.value)
                                    }
                                    className="h-11 border-admin-border bg-admin-canvas"
                                />
                            </div>
                        </>
                    )}
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

            {findings.data.length === 0 ? (
                <p
                    role="status"
                    className="px-5 py-14 text-center text-sm text-admin-text-tertiary"
                >
                    No hay findings para los filtros elegidos.
                </p>
            ) : (
                <div className="grid gap-3 p-4 sm:p-5 lg:grid-cols-2">
                    {findings.data.map((finding) => (
                        <FindingCard key={finding.key} finding={finding} />
                    ))}
                </div>
            )}

            <nav
                aria-label="Paginación de findings"
                className="flex flex-col gap-3 border-t border-admin-border px-4 py-4 sm:flex-row sm:items-center sm:justify-between sm:px-5"
            >
                <p className="text-sm text-admin-text-tertiary">
                    {findings.total} findings · página {findings.current_page}{' '}
                    de {findings.last_page}
                </p>
                <div className="flex gap-2">
                    <AuditPageLink
                        href={findings.prev_page_url}
                        label="Página anterior"
                    >
                        <ChevronLeft />
                    </AuditPageLink>
                    <AuditPageLink
                        href={findings.next_page_url}
                        label="Página siguiente"
                    >
                        <ChevronRight />
                    </AuditPageLink>
                </div>
            </nav>
        </section>
    );
}

function FindingCard({ finding }: { finding: ManagementAuditFinding }) {
    const action = findingAction(finding);

    return (
        <article className="min-w-0 rounded-lg border border-admin-border bg-admin-surface-raised p-4">
            <div className="flex min-w-0 items-start justify-between gap-3">
                <div className="min-w-0">
                    <p className="text-xs font-medium tracking-wide text-admin-text-tertiary uppercase">
                        {finding.entity_label ??
                            finding.entity_type ??
                            'Auditoría financiera'}
                    </p>
                    <h3 className="mt-1 text-base font-semibold text-admin-text-primary">
                        {finding.title}
                    </h3>
                </div>
                <SeverityBadge severity={finding.severity} />
            </div>
            <p className="mt-3 text-sm text-admin-text-secondary">
                {finding.description}
            </p>

            <dl className="mt-4 grid gap-3 sm:grid-cols-2">
                {finding.amount != null && (
                    <AuditDetail
                        label="Importe"
                        value={formatMoney(finding.amount)}
                        numeric
                    />
                )}
                {finding.wallet && (
                    <AuditDetail
                        label="Billetera"
                        value={`${finding.wallet.name}${finding.wallet.archived_at ? ' (archivada)' : ''}`}
                    />
                )}
                {finding.effective_date && (
                    <AuditDetail
                        label="Fecha"
                        value={formatManagementDate(finding.effective_date)}
                    />
                )}
                {finding.age_days != null && (
                    <AuditDetail
                        label="Antigüedad"
                        value={ageLabel(finding.age_days)}
                    />
                )}
                {finding.expected != null && (
                    <AuditDetail
                        label="Esperado"
                        value={findingComparisonValue(
                            finding,
                            finding.expected,
                        )}
                    />
                )}
                {finding.found != null && (
                    <AuditDetail
                        label="Encontrado"
                        value={findingComparisonValue(finding, finding.found)}
                    />
                )}
            </dl>

            {action ? (
                <Button asChild variant="outline" className="mt-4 h-11 w-full">
                    <Link href={action.href}>{action.label}</Link>
                </Button>
            ) : (
                <p className="mt-4 border-t border-admin-border pt-3 text-xs text-admin-text-tertiary">
                    Sólo lectura · revisá el detalle de la entidad relacionada
                    antes de tomar una decisión.
                </p>
            )}
        </article>
    );
}

function WalletBalanceAudit({
    wallets,
}: {
    wallets: ManagementAuditData['wallet_balances'];
}) {
    return (
        <section className="rounded-xl border border-admin-border bg-admin-surface p-4 sm:p-5">
            <header>
                <h2 className="text-xl font-semibold text-admin-text-primary">
                    Saldos por billetera
                </h2>
                <p className="mt-1 text-sm text-admin-text-tertiary">
                    Saldos calculados exclusivamente desde las entradas del
                    ledger.
                </p>
            </header>
            {wallets.length === 0 ? (
                <p
                    role="status"
                    className="mt-4 rounded-lg border border-dashed border-admin-border px-5 py-10 text-center text-sm text-admin-text-tertiary"
                >
                    No hay billeteras para el período elegido.
                </p>
            ) : (
                <div className="mt-4 grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
                    {wallets.map((wallet) => (
                        <article
                            key={wallet.wallet_id}
                            className="min-w-0 rounded-lg border border-admin-border bg-admin-surface-raised p-4"
                        >
                            <div className="flex items-start justify-between gap-3">
                                <div className="min-w-0">
                                    <p className="truncate text-sm font-medium text-admin-text-secondary">
                                        {wallet.wallet_name}
                                        {wallet.archived_at
                                            ? ' (archivada)'
                                            : ''}
                                    </p>
                                    <p
                                        className={cn(
                                            'mt-2 text-2xl font-semibold text-admin-text-primary tabular-nums',
                                            wallet.derived_balance < 0 &&
                                                'text-destructive',
                                        )}
                                    >
                                        {formatMoney(wallet.derived_balance)}
                                    </p>
                                </div>
                                <WalletStatusBadge status={wallet.status} />
                            </div>
                            <dl className="mt-4 grid grid-cols-2 gap-3">
                                <AuditDetail
                                    label="Movimientos"
                                    value={wallet.transaction_count.toString()}
                                    numeric
                                />
                                <AuditDetail
                                    label="Último movimiento"
                                    value={
                                        wallet.last_transaction_at
                                            ? formatManagementDate(
                                                  wallet.last_transaction_at,
                                              )
                                            : 'Sin movimientos'
                                    }
                                />
                            </dl>
                        </article>
                    ))}
                </div>
            )}
        </section>
    );
}

function AuditSelect({
    id,
    label,
    value,
    onValueChange,
    options,
}: {
    id: string;
    label: string;
    value: string;
    onValueChange: (value: string) => void;
    options: Array<{ value: string; label: string }>;
}) {
    return (
        <div className="grid gap-1.5">
            <Label htmlFor={id}>{label}</Label>
            <Select value={value} onValueChange={onValueChange}>
                <SelectTrigger
                    id={id}
                    className="h-11 w-full border-admin-border bg-admin-canvas"
                >
                    <SelectValue />
                </SelectTrigger>
                <SelectContent>
                    <SelectItem value="all">Todos</SelectItem>
                    {options.map((option) => (
                        <SelectItem key={option.value} value={option.value}>
                            {option.label}
                        </SelectItem>
                    ))}
                </SelectContent>
            </Select>
        </div>
    );
}

function SeverityBadge({ severity }: { severity: ManagementAuditSeverity }) {
    const labels = { info: 'Info', warning: 'Warning', critical: 'Crítico' };

    return (
        <Badge
            variant={severity === 'critical' ? 'destructive' : 'outline'}
            className={cn(
                severity !== 'critical' && 'border-admin-border',
                severity === 'warning'
                    ? 'text-warning'
                    : severity === 'info' && 'text-admin-accent-text',
            )}
        >
            {labels[severity]}
        </Badge>
    );
}

function WalletStatusBadge({
    status,
}: {
    status: 'ok' | 'warning' | 'critical';
}) {
    return (
        <Badge
            variant={status === 'critical' ? 'destructive' : 'outline'}
            className={cn(
                status !== 'critical' && 'border-admin-border',
                status === 'ok'
                    ? 'text-admin-accent-text'
                    : status === 'warning' && 'text-warning',
            )}
        >
            {status === 'ok'
                ? 'OK'
                : status === 'warning'
                  ? 'Warning'
                  : 'Crítico'}
        </Badge>
    );
}

function AuditDetail({
    label,
    value,
    numeric = false,
}: {
    label: string;
    value: string;
    numeric?: boolean;
}) {
    return (
        <div className="min-w-0">
            <dt className="text-xs font-medium text-admin-text-tertiary">
                {label}
            </dt>
            <dd
                className={cn(
                    'mt-1 text-sm break-words text-admin-text-primary',
                    numeric && 'font-semibold tabular-nums',
                )}
            >
                {value}
            </dd>
        </div>
    );
}

function AuditPageLink({
    href,
    label,
    children,
}: {
    href: string | null;
    label: string;
    children: React.ReactNode;
}) {
    if (!href) {
        return (
            <Button
                type="button"
                variant="outline"
                size="icon"
                disabled
                aria-label={label}
                className="size-11"
            >
                {children}
            </Button>
        );
    }

    return (
        <Button asChild variant="outline" size="icon" className="size-11">
            <Link href={href} preserveScroll aria-label={label}>
                {children}
            </Link>
        </Button>
    );
}

function ageLabel(days: number) {
    if (days === 0) {
        return 'Menos de un día';
    }

    return `${days} ${days === 1 ? 'día' : 'días'}`;
}

function findingAction(finding: ManagementAuditFinding) {
    if (finding.type === 'pending_distribution') {
        const transactionFilter = finding.financial_transaction_id
            ? `&transaction_id=${finding.financial_transaction_id}`
            : '';

        return {
            label: 'Distribuir',
            href: `/admin/management?type=order_payment${transactionFilter}`,
        };
    }

    if (finding.financial_transaction_id) {
        return {
            label: 'Ver detalle',
            href: `/admin/management?transaction_id=${finding.financial_transaction_id}`,
        };
    }

    if (finding.entity_type === 'configuration') {
        return { label: 'Ver Gestión', href: '/admin/management' };
    }

    return null;
}

function findingComparisonValue(
    finding: ManagementAuditFinding,
    value: string | number,
) {
    return finding.type === 'amount_mismatch' && typeof value === 'number'
        ? formatMoney(value)
        : String(value);
}
