import {
    CircleDollarSign,
    Clock3,
    ShoppingBasket,
    WalletCards,
} from 'lucide-react';
import type { ReactNode } from 'react';
import {
    dashboardCollectedLabel,
    dashboardSalesLabel,
} from '@/features/dashboard/presentation';
import type { DashboardData } from '@/features/dashboard/types';
import { formatMoney } from '@/lib/format';
import { cn } from '@/lib/utils';

function KpiCard({
    icon,
    label,
    value,
    detail,
    attention = false,
}: {
    icon: ReactNode;
    label: string;
    value: string;
    detail: string;
    attention?: boolean;
}) {
    return (
        <article
            className={cn(
                'grid min-h-36 content-between gap-5 rounded-xl border border-admin-border bg-admin-surface p-4 sm:p-5',
                attention && 'border-warning/40 bg-warning-subtle',
            )}
        >
            <div className="flex items-start justify-between gap-4">
                <div className="grid size-10 place-items-center rounded-lg bg-admin-canvas text-admin-accent-text">
                    {icon}
                </div>
                {attention && (
                    <span className="rounded-full bg-warning-subtle px-2.5 py-1 text-xs font-semibold text-warning">
                        Requiere atención
                    </span>
                )}
            </div>
            <div>
                <p className="text-2xl font-semibold text-admin-text-primary tabular-nums sm:text-3xl">
                    {value}
                </p>
                <h2 className="mt-1 text-sm font-medium text-admin-text-secondary">
                    {label}
                </h2>
                <p className="mt-2 text-xs text-admin-text-tertiary">
                    {detail}
                </p>
            </div>
        </article>
    );
}

export function DashboardKpis({ dashboard }: { dashboard: DashboardData }) {
    const { kpis, period } = dashboard;

    return (
        <section
            aria-label="Indicadores del período"
            data-testid="dashboard-kpis"
            className="grid gap-3 sm:grid-cols-2 xl:grid-cols-4"
        >
            <KpiCard
                icon={
                    <CircleDollarSign className="size-5" aria-hidden="true" />
                }
                label={dashboardSalesLabel(period)}
                value={formatMoney(kpis.sales_total)}
                detail={
                    kpis.sales_total > 0
                        ? 'Venta confirmada, independientemente del pago.'
                        : 'Todavía no se registraron ventas en este período.'
                }
            />
            <KpiCard
                icon={<WalletCards className="size-5" aria-hidden="true" />}
                label={dashboardCollectedLabel(period)}
                value={formatMoney(kpis.collected_total)}
                detail={`${kpis.unpaid_count} ${kpis.unpaid_count === 1 ? 'pedido confirmado sin cobrar' : 'pedidos confirmados sin cobrar'}`}
            />
            <KpiCard
                icon={<ShoppingBasket className="size-5" aria-hidden="true" />}
                label={
                    period === 'today'
                        ? 'Pedidos de hoy'
                        : 'Pedidos del período'
                }
                value={kpis.orders_count.toString()}
                detail={`${kpis.empanadas_count} ${kpis.empanadas_count === 1 ? 'unidad de producto vendida' : 'unidades de productos vendidas'}`}
            />
            <KpiCard
                icon={<Clock3 className="size-5" aria-hidden="true" />}
                label="Por confirmar hoy"
                value={kpis.pending_confirmation_count.toString()}
                detail="Siempre muestra lo urgente de hoy."
                attention={kpis.pending_confirmation_count > 0}
            />
        </section>
    );
}
