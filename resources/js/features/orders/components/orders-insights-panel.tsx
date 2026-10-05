import { ChevronDown, Utensils } from 'lucide-react';
import { useState } from 'react';

import {
    deliveryTypeLabels,
    orderConditionLabels,
} from '@/features/orders/presentation';
import type {
    OrderInsightsCondition,
    OrderInsightsDeliveryType,
    OrderInsightsProduct,
} from '@/features/orders/types';

const deliveryColors = {
    pickup: '#FFB572',
    dine_in: '#50D1AA',
    delivery: '#65B0F6',
} as const;

const conditionColors = {
    raw: '#9288E0',
    cooked: '#50D1AA',
} as const;

const ringRadii = [44, 35] as const;

interface DistributionItem {
    key: string;
    label: string;
    count: number;
    color: string;
}

interface OrdersInsightsPanelProps {
    topProducts: OrderInsightsProduct[];
    deliveryTypes: OrderInsightsDeliveryType[];
    conditions: OrderInsightsCondition[];
    onViewAll?: () => void;
}

function PeriodControl() {
    return (
        <div
            aria-label="Período: hoy"
            className="flex h-[45px] w-[95px] items-center justify-center gap-2.5 rounded-lg border border-admin-border text-sm font-medium text-white"
        >
            <ChevronDown aria-hidden="true" className="size-5" />
            <span>Hoy</span>
        </div>
    );
}

function ProductArtwork({ product }: { product: OrderInsightsProduct }) {
    const [failed, setFailed] = useState(false);

    if (!product.image_url || failed) {
        return (
            <span className="flex size-14 shrink-0 items-center justify-center rounded-full bg-admin-surface-raised text-admin-accent">
                <Utensils
                    aria-hidden="true"
                    className="size-6"
                    strokeWidth={1.7}
                />
            </span>
        );
    }

    return (
        <img
            src={product.image_url}
            alt=""
            className="size-14 shrink-0 rounded-full object-cover"
            onError={() => setFailed(true)}
        />
    );
}

function DistributionDonut({
    title,
    items,
}: {
    title: string;
    items: DistributionItem[];
}) {
    const total = items.reduce((sum, item) => sum + item.count, 0);
    const description = items
        .map((item) => `${item.label}: ${item.count}`)
        .join(', ');

    return (
        <section className="flex min-w-0 flex-col items-center">
            <h3 className="text-sm leading-[1.3] font-medium text-white">
                {title}
            </h3>
            <svg
                role="img"
                aria-label={`${title}. ${description || 'Sin pedidos'}`}
                viewBox="0 0 112 112"
                className="mt-2 size-28 shrink-0"
            >
                {ringRadii.map((radius) => (
                    <circle
                        key={`background-${radius}`}
                        cx="56"
                        cy="56"
                        r={radius}
                        fill="none"
                        stroke="#2D303E"
                        strokeWidth="8"
                    />
                ))}

                {items.slice(0, ringRadii.length).map((item, index) => {
                    const radius = ringRadii[index];
                    const circumference = 2 * Math.PI * radius;
                    const ratio = total > 0 ? item.count / total : 0;

                    return (
                        <circle
                            key={item.key}
                            cx="56"
                            cy="56"
                            r={radius}
                            fill="none"
                            stroke={item.color}
                            strokeWidth="8"
                            strokeLinecap="round"
                            strokeDasharray={`${circumference * ratio} ${circumference}`}
                            transform="rotate(-90 56 56)"
                        />
                    );
                })}
            </svg>

            <ul className="mt-2 grid w-full gap-1.5">
                {items.map((item) => (
                    <li
                        key={item.key}
                        className="flex min-w-0 items-center gap-2"
                    >
                        <span
                            aria-hidden="true"
                            className="size-2.5 shrink-0 rounded-full"
                            style={{ backgroundColor: item.color }}
                        />
                        <span className="truncate text-xs text-admin-text-secondary">
                            <span className="font-medium text-white">
                                {item.label}
                            </span>{' '}
                            · {item.count}
                        </span>
                    </li>
                ))}
            </ul>
        </section>
    );
}

export function OrdersInsightsPanel({
    topProducts,
    deliveryTypes,
    conditions,
    onViewAll,
}: OrdersInsightsPanelProps) {
    const visibleProducts = topProducts.slice(0, 3);
    const deliveryDistribution: DistributionItem[] = deliveryTypes.map(
        (item) => ({
            key: item.type,
            label: deliveryTypeLabels[item.type],
            count: item.orders_count,
            color: deliveryColors[item.type],
        }),
    );
    const conditionDistribution: DistributionItem[] = [...conditions]
        .sort(
            (left, right) =>
                Number(left.condition === 'raw') -
                Number(right.condition === 'raw'),
        )
        .map((item) => ({
            key: item.condition,
            label: orderConditionLabels[item.condition],
            count: item.orders_count,
            color: conditionColors[item.condition],
        }));

    return (
        <aside
            aria-label="Resumen de pedidos"
            data-testid="orders-insights-panel"
            className="grid w-full min-w-0 gap-6 font-orders min-[900px]:grid-cols-2 xl:h-full xl:grid-cols-1 xl:grid-rows-[minmax(0,429fr)_minmax(0,333fr)]"
        >
            <section
                data-testid="orders-insights-products"
                className="flex min-h-[429px] flex-col gap-5 rounded-lg bg-admin-surface p-6 xl:h-full xl:min-h-0 xl:overflow-hidden"
            >
                <header className="flex h-[45px] shrink-0 items-center justify-between">
                    <h2 className="text-xl leading-[1.4] font-semibold text-white">
                        Más pedidos
                    </h2>
                    <PeriodControl />
                </header>

                <div className="h-px shrink-0 bg-admin-border" />

                <div className="flex min-h-0 flex-1 flex-col justify-between gap-5">
                    {visibleProducts.length > 0 ? (
                        <ol className="flex flex-col gap-6">
                            {visibleProducts.map((product) => (
                                <li
                                    key={product.product_id}
                                    className="flex h-14 min-w-0 items-start gap-4"
                                >
                                    <ProductArtwork product={product} />
                                    <div className="min-w-0 space-y-1 pt-0.5">
                                        <p className="truncate text-sm leading-[1.3] font-medium text-[#E0E6E9]">
                                            {product.name}
                                        </p>
                                        <p className="text-xs leading-[1.4] text-admin-text-secondary">
                                            {product.units_count}{' '}
                                            {product.units_count === 1
                                                ? 'unidad pedida'
                                                : 'unidades pedidas'}
                                        </p>
                                    </div>
                                </li>
                            ))}
                        </ol>
                    ) : (
                        <p className="py-8 text-sm text-admin-text-secondary">
                            Todavía no hay productos para mostrar.
                        </p>
                    )}

                    <button
                        type="button"
                        aria-disabled={!onViewAll}
                        onClick={onViewAll}
                        className="h-12 w-full shrink-0 rounded-lg border border-admin-accent text-sm leading-[1.4] font-semibold text-admin-accent focus-visible:ring-2 focus-visible:ring-admin-accent focus-visible:ring-offset-2 focus-visible:ring-offset-admin-surface focus-visible:outline-none"
                    >
                        Ver todos
                    </button>
                </div>
            </section>

            <section
                data-testid="orders-insights-delivery"
                className="flex min-h-[333px] flex-col rounded-lg bg-admin-surface pb-10 xl:h-full xl:min-h-0 xl:overflow-hidden"
            >
                <header className="flex h-[86px] shrink-0 items-start justify-between border-b border-admin-border px-6 pt-6">
                    <h2 className="text-xl leading-[1.4] font-semibold text-white">
                        Distribución de pedidos
                    </h2>
                    <PeriodControl />
                </header>

                <div className="grid min-h-0 flex-1 grid-cols-2 items-center gap-6 px-6 py-4">
                    <DistributionDonut
                        title="Entrega"
                        items={deliveryDistribution}
                    />
                    <DistributionDonut
                        title="Condición"
                        items={conditionDistribution}
                    />
                </div>
            </section>
        </aside>
    );
}
