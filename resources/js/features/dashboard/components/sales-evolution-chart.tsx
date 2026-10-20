import { DashboardCard } from '@/features/dashboard/components/dashboard-card';
import {
    dashboardPeriodLabels,
    formatDashboardBucket,
    formatDashboardBucketAccessible,
} from '@/features/dashboard/presentation';
import type { DashboardData } from '@/features/dashboard/types';
import { formatMoney } from '@/lib/format';

const width = 640;
const height = 220;
const insetX = 76;
const insetRight = 18;
const insetTop = 18;
const insetBottom = 34;

export function SalesEvolutionChart({
    period,
    points,
}: {
    period: DashboardData['period'];
    points: DashboardData['evolution'];
}) {
    const maximum = Math.max(...points.map((point) => point.total), 0);
    const plotHeight = height - insetTop - insetBottom;
    const plotWidth = width - insetX - insetRight;
    const coordinates = points.map((point, index) => ({
        ...point,
        x:
            points.length <= 1
                ? width / 2
                : insetX + (index / (points.length - 1)) * plotWidth,
        y:
            maximum <= 0
                ? insetTop + plotHeight
                : insetTop + plotHeight * (1 - point.total / maximum),
    }));
    const linePath = coordinates
        .map(
            (point, index) =>
                `${index === 0 ? 'M' : 'L'} ${point.x} ${point.y}`,
        )
        .join(' ');
    const areaPath = coordinates.length
        ? `${linePath} L ${coordinates.at(-1)?.x} ${height - insetBottom} L ${coordinates[0].x} ${height - insetBottom} Z`
        : '';
    const labelIndexes = new Set(
        points.length <= 7
            ? points.map((_, index) => index)
            : [0, Math.floor((points.length - 1) / 2), points.length - 1],
    );
    const total = points.reduce((sum, point) => sum + point.total, 0);

    return (
        <DashboardCard
            title="Evolución de ventas"
            description={`Venta confirmada por fecha de entrega · ${dashboardPeriodLabels[period]}`}
            data-testid="dashboard-sales-evolution"
            className="h-full"
        >
            {maximum <= 0 || points.length === 0 ? (
                <p className="rounded-lg bg-admin-surface-raised px-4 py-16 text-center text-sm text-admin-text-secondary">
                    Todavía no se registraron ventas en este período.
                </p>
            ) : (
                <div className="min-w-0">
                    <div className="mb-2 flex flex-wrap items-center justify-between gap-2 text-xs text-admin-text-tertiary">
                        <span>
                            Total:{' '}
                            <strong className="font-semibold text-admin-text-primary tabular-nums">
                                {formatMoney(total)}
                            </strong>
                        </span>
                        <span>
                            Máximo por tramo:{' '}
                            <strong className="font-semibold text-admin-text-primary tabular-nums">
                                {formatMoney(maximum)}
                            </strong>
                        </span>
                    </div>
                    <svg
                        role="img"
                        aria-labelledby="dashboard-sales-chart-title dashboard-sales-chart-description"
                        viewBox={`0 0 ${width} ${height}`}
                        className="h-auto min-h-48 w-full overflow-visible"
                    >
                        <title id="dashboard-sales-chart-title">
                            Evolución de ventas
                        </title>
                        <desc id="dashboard-sales-chart-description">
                            Importe de ventas confirmadas agrupado por fecha de
                            entrega.
                        </desc>
                        <defs>
                            <linearGradient
                                id="dashboard-sales-area"
                                x1="0"
                                x2="0"
                                y1="0"
                                y2="1"
                            >
                                <stop
                                    offset="0%"
                                    stopColor="var(--brand-accent)"
                                    stopOpacity="0.38"
                                />
                                <stop
                                    offset="100%"
                                    stopColor="var(--brand-accent)"
                                    stopOpacity="0"
                                />
                            </linearGradient>
                        </defs>
                        {[0, 1, 2, 3].map((line) => {
                            const y = insetTop + (line / 3) * plotHeight;
                            const value = maximum * (1 - line / 3);

                            return (
                                <g key={line}>
                                    <text
                                        x="0"
                                        y={y + 4}
                                        fill="var(--admin-text-tertiary)"
                                        fontSize="11"
                                    >
                                        {formatMoney(value)}
                                    </text>
                                    <line
                                        x1={insetX}
                                        x2={width - insetRight}
                                        y1={y}
                                        y2={y}
                                        stroke="var(--admin-border)"
                                        strokeWidth="1"
                                    />
                                </g>
                            );
                        })}
                        <path d={areaPath} fill="url(#dashboard-sales-area)" />
                        <path
                            d={linePath}
                            fill="none"
                            stroke="var(--brand-accent)"
                            strokeWidth="3"
                            strokeLinecap="round"
                            strokeLinejoin="round"
                            vectorEffect="non-scaling-stroke"
                        />
                        {coordinates.map((point) => (
                            <circle
                                key={point.bucket}
                                cx={point.x}
                                cy={point.y}
                                r="4"
                                fill="var(--brand-accent)"
                                stroke="var(--admin-surface)"
                                strokeWidth="2"
                                vectorEffect="non-scaling-stroke"
                            >
                                <title>{`${formatDashboardBucketAccessible(point.bucket, period)}: ${formatMoney(point.total)}`}</title>
                            </circle>
                        ))}
                    </svg>
                    <div className="mt-1 flex justify-between gap-3 text-xs text-admin-text-tertiary">
                        {points.map((point, index) =>
                            labelIndexes.has(index) ? (
                                <span key={point.bucket}>
                                    {formatDashboardBucket(
                                        point.bucket,
                                        period,
                                    )}
                                </span>
                            ) : null,
                        )}
                    </div>
                    <ol className="sr-only">
                        {points.map((point) => (
                            <li key={point.bucket}>
                                {formatDashboardBucketAccessible(
                                    point.bucket,
                                    period,
                                )}
                                : {formatMoney(point.total)}
                            </li>
                        ))}
                    </ol>
                </div>
            )}
        </DashboardCard>
    );
}
