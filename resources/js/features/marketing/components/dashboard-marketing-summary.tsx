import { Link } from '@inertiajs/react';
import { ArrowUpRight, Megaphone, TriangleAlert } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { DashboardCard } from '@/features/dashboard/components/dashboard-card';
import { formatMarketingDate, visibilityReasonLabels } from '../presentation';
import type { MarketingDashboardSummary } from '../types';

export function DashboardMarketingSummary({
    summary,
}: {
    summary: MarketingDashboardSummary;
}) {
    return (
        <DashboardCard
            title="Marketing"
            description="Recomendación de la casa."
            data-testid="dashboard-marketing-summary"
            action={
                <div className="grid size-9 place-items-center rounded-lg bg-admin-selection text-admin-accent-text">
                    <Megaphone className="size-4" aria-hidden="true" />
                </div>
            }
        >
            <div className="rounded-lg bg-admin-surface-raised p-3">
                <MarketingSummaryContent summary={summary} />
            </div>
            <Button
                asChild
                variant="ghost"
                size="sm"
                className="mt-3 w-full justify-between text-admin-accent-text hover:text-admin-text-primary"
            >
                <Link href="/admin/marketing">
                    Gestionar marketing
                    <ArrowUpRight aria-hidden="true" />
                </Link>
            </Button>
        </DashboardCard>
    );
}

function MarketingSummaryContent({
    summary,
}: {
    summary: MarketingDashboardSummary;
}) {
    if (summary.state === 'none') {
        return (
            <>
                <p className="text-sm font-semibold text-admin-text-primary">
                    Sin recomendación activa
                </p>
                <p className="mt-1 text-xs text-admin-text-tertiary">
                    No hay productos destacados programados.
                </p>
            </>
        );
    }

    if (summary.state === 'upcoming') {
        return (
            <>
                <p className="text-xs font-medium text-admin-text-tertiary">
                    Próxima recomendación
                </p>
                <p className="mt-1 truncate text-sm font-semibold text-admin-text-primary">
                    {summary.product_name}
                </p>
                <p className="mt-1 text-xs text-admin-text-secondary">
                    Comienza{' '}
                    {formatMarketingDate(
                        summary.starts_at,
                        summary.business_timezone,
                    )}
                </p>
            </>
        );
    }

    if (summary.state === 'active_hidden') {
        return (
            <>
                <p className="flex items-center gap-1.5 text-xs font-medium text-warning">
                    <TriangleAlert className="size-3.5" aria-hidden="true" />
                    Recomendación no visible
                </p>
                <p className="mt-1 truncate text-sm font-semibold text-admin-text-primary">
                    {summary.product_name}
                </p>
                <p className="mt-1 text-xs text-admin-text-secondary">
                    {summary.visibility_reason
                        ? visibilityReasonLabels[summary.visibility_reason]
                        : 'El producto no está disponible públicamente.'}
                </p>
            </>
        );
    }

    return (
        <>
            <p className="text-xs font-medium text-success">
                Recomendación activa
            </p>
            <p className="mt-1 truncate text-sm font-semibold text-admin-text-primary">
                {summary.product_name}
            </p>
            <p className="mt-1 text-xs text-admin-text-secondary">
                {summary.ends_at
                    ? `Hasta ${formatMarketingDate(summary.ends_at, summary.business_timezone)}`
                    : 'Sin fecha de finalización'}
            </p>
        </>
    );
}
