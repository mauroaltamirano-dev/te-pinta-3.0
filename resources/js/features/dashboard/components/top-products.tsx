import { DashboardCard } from '@/features/dashboard/components/dashboard-card';
import {
    formatDashboardDozens,
    formatDashboardNumber,
} from '@/features/dashboard/presentation';
import type { DashboardData } from '@/features/dashboard/types';

export function TopProducts({
    products,
}: {
    products: DashboardData['top_products'];
}) {
    return (
        <DashboardCard
            title="Productos más vendidos"
            description="Todas las variedades, ordenadas por unidades vendidas."
            data-testid="dashboard-top-products"
            className="h-full"
        >
            {products.length === 0 ? (
                <p className="rounded-lg bg-admin-surface-raised px-4 py-8 text-center text-sm text-admin-text-secondary">
                    Todavía no hay productos vendidos en este período.
                </p>
            ) : (
                <ol className="grid gap-2">
                    {products.map((product, index) => (
                        <li
                            key={product.product_id}
                            className="grid grid-cols-[2rem_minmax(0,1fr)_auto] items-center gap-3 rounded-lg bg-admin-surface-raised px-3 py-3"
                        >
                            <span className="grid size-8 place-items-center rounded-lg bg-admin-canvas text-sm font-semibold text-admin-accent tabular-nums">
                                {index + 1}
                            </span>
                            <span className="min-w-0 truncate text-sm font-medium text-white">
                                {product.name}
                            </span>
                            <span className="text-right text-sm font-semibold text-admin-text-secondary tabular-nums">
                                {formatDashboardDozens.format(
                                    product.units_count / 12,
                                )}{' '}
                                {product.units_count === 12
                                    ? 'docena'
                                    : 'docenas'}{' '}
                                <span className="whitespace-nowrap text-admin-text-tertiary">
                                    (
                                    {formatDashboardNumber.format(
                                        product.units_count,
                                    )}{' '}
                                    {product.units_count === 1
                                        ? 'unidad'
                                        : 'unidades'}
                                    )
                                </span>
                            </span>
                        </li>
                    ))}
                </ol>
            )}
        </DashboardCard>
    );
}
