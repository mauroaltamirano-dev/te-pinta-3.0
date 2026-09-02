import type { ReactNode } from 'react';
import { DashboardCard } from '@/features/dashboard/components/dashboard-card';
import {
    formatDashboardNumber,
    formatDashboardPercentage,
} from '@/features/dashboard/presentation';
import type { DashboardData } from '@/features/dashboard/types';
import {
    deliveryTypeLabels,
    orderConditionLabels,
} from '@/features/orders/presentation';

interface DistributionItem {
    key: string;
    label: string;
    value: number;
    color: string;
}

function DistributionBlock({
    title,
    unit,
    items,
    empty,
}: {
    title: string;
    unit: ReactNode;
    items: DistributionItem[];
    empty: string;
}) {
    const total = items.reduce((sum, item) => sum + item.value, 0);

    return (
        <section aria-labelledby={`distribution-${items[0]?.key ?? title}`}>
            <div className="flex items-baseline justify-between gap-3">
                <h3
                    id={`distribution-${items[0]?.key ?? title}`}
                    className="text-sm font-semibold text-white"
                >
                    {title}
                </h3>
                <span className="text-xs text-admin-text-tertiary">{unit}</span>
            </div>
            {total === 0 ? (
                <p className="mt-3 rounded-lg bg-admin-surface-raised px-3 py-5 text-sm text-admin-text-secondary">
                    {empty}
                </p>
            ) : (
                <>
                    <div
                        aria-hidden="true"
                        className="mt-3 flex h-3 overflow-hidden rounded-full bg-admin-canvas"
                    >
                        {items.map((item) => (
                            <span
                                key={item.key}
                                className={item.color}
                                style={{
                                    width: `${(item.value / total) * 100}%`,
                                }}
                            />
                        ))}
                    </div>
                    <dl className="mt-3 grid gap-2">
                        {items.map((item) => (
                            <div
                                key={item.key}
                                className="flex items-center justify-between gap-3 text-sm"
                            >
                                <dt className="flex items-center gap-2 text-admin-text-secondary">
                                    <span
                                        aria-hidden="true"
                                        className={`size-2.5 rounded-full ${item.color}`}
                                    />
                                    {item.label}
                                </dt>
                                <dd className="font-medium text-white tabular-nums">
                                    {formatDashboardNumber.format(item.value)} ·{' '}
                                    {formatDashboardPercentage.format(
                                        item.value / total,
                                    )}
                                </dd>
                            </div>
                        ))}
                    </dl>
                </>
            )}
        </section>
    );
}

export function OrderDistributions({
    dashboard,
}: {
    dashboard: DashboardData;
}) {
    const deliveryItems: DistributionItem[] =
        dashboard.delivery_distribution.map((item) => ({
            key: item.type,
            label: deliveryTypeLabels[item.type],
            value: item.orders_count,
            color: item.type === 'delivery' ? 'bg-[#65b0f6]' : 'bg-[#ffb572]',
        }));
    const conditionItems: DistributionItem[] = [
        ...dashboard.condition_distribution,
    ]
        .sort(
            (left, right) =>
                Number(left.condition === 'raw') -
                Number(right.condition === 'raw'),
        )
        .map((item) => ({
            key: item.condition,
            label: orderConditionLabels[item.condition],
            value: item.units_count,
            color: item.condition === 'raw' ? 'bg-[#9288e0]' : 'bg-[#50d1aa]',
        }));

    return (
        <DashboardCard
            title="Distribución del período"
            description="Cómo se entregan y producen los pedidos."
            data-testid="dashboard-distributions"
            className="h-full"
            contentClassName="grid gap-6"
        >
            <DistributionBlock
                title="Envío y retiro"
                unit="por pedidos"
                items={deliveryItems}
                empty="No hay pedidos para distribuir."
            />
            <div className="h-px bg-admin-border" />
            <DistributionBlock
                title="Cocinadas y crudas"
                unit="por empanadas"
                items={conditionItems}
                empty="No hay empanadas para distribuir."
            />
        </DashboardCard>
    );
}
