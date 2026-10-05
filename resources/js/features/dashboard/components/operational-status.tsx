import { DashboardCard } from '@/features/dashboard/components/dashboard-card';
import {
    dashboardStatusLabels,
    formatDashboardNumber,
} from '@/features/dashboard/presentation';
import type { DashboardData } from '@/features/dashboard/types';
import { orderStatusStyles } from '@/features/orders/presentation';

export function OperationalStatus({
    statuses,
    empanadasCount,
}: {
    statuses: DashboardData['today_status_distribution'];
    empanadasCount: number;
}) {
    return (
        <DashboardCard
            title="Estado de hoy"
            description="Carga operativa por estado."
            data-testid="dashboard-operational-status"
            className="flex h-full flex-col"
            contentClassName="flex flex-1 flex-col justify-between"
        >
            <dl className="grid grid-cols-2 gap-2">
                {statuses.map((item) => (
                    <div
                        key={item.status}
                        className="flex min-w-0 items-center justify-between gap-2 rounded-lg bg-admin-surface-raised px-3 py-3"
                    >
                        <dt className="flex min-w-0 items-center gap-2 text-sm text-admin-text-secondary">
                            <span
                                aria-hidden="true"
                                className={`size-2.5 rounded-full ${orderStatusStyles[item.status].split(' ')[0]}`}
                            />
                            <span className="truncate">
                                {dashboardStatusLabels[item.status]}
                            </span>
                        </dt>
                        <dd className="text-lg font-semibold text-white tabular-nums">
                            {item.orders_count}
                        </dd>
                    </div>
                ))}
            </dl>
            <dl className="mt-5 border-t border-admin-border pt-5">
                <div className="rounded-lg bg-admin-canvas px-3 py-3">
                    <dt className="text-xs text-admin-text-tertiary">
                        Unidades de productos vendidas
                    </dt>
                    <dd className="mt-1 text-lg font-semibold text-white tabular-nums">
                        {formatDashboardNumber.format(empanadasCount)}
                    </dd>
                </div>
            </dl>
        </DashboardCard>
    );
}
