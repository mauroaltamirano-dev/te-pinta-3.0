import { Head, Link, router } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { DashboardKpis } from '@/features/dashboard/components/dashboard-kpis';
import { OperationalStatus } from '@/features/dashboard/components/operational-status';
import { OrderDistributions } from '@/features/dashboard/components/order-distributions';
import { PeriodSelector } from '@/features/dashboard/components/period-selector';
import { SalesEvolutionChart } from '@/features/dashboard/components/sales-evolution-chart';
import { TodayOrders } from '@/features/dashboard/components/today-orders';
import { TopProducts } from '@/features/dashboard/components/top-products';
import { UrgentOrders } from '@/features/dashboard/components/urgent-orders';
import type {
    DashboardPageProps,
    DashboardPeriod,
} from '@/features/dashboard/types';
import { DashboardMarketingSummary } from '@/features/marketing/components/dashboard-marketing-summary';
import { OrderDetailSheet } from '@/features/orders/components/order-detail-sheet';

export default function Dashboard({
    dashboard,
    marketingSummary,
    selectedOrder = null,
    selectedOrderSummary = null,
}: DashboardPageProps) {
    const selectPeriod = (period: DashboardPeriod) =>
        router.get(
            '/admin/dashboard',
            { period },
            {
                preserveState: true,
                preserveScroll: true,
                replace: true,
                only: ['dashboard'],
            },
        );
    const openOrder = (order: string) =>
        router.get(
            '/admin/dashboard',
            { period: dashboard.period, order },
            {
                preserveState: true,
                preserveScroll: true,
                only: ['selectedOrder', 'selectedOrderSummary'],
            },
        );
    const closeOrder = () =>
        router.get(
            '/admin/dashboard',
            { period: dashboard.period },
            {
                preserveState: true,
                preserveScroll: true,
                replace: true,
                only: ['selectedOrder', 'selectedOrderSummary'],
            },
        );

    return (
        <>
            <Head title="Panel" />
            <section className="grid gap-5 font-orders sm:gap-6">
                <header className="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
                    <div>
                        <p className="text-xs font-semibold tracking-[0.16em] text-admin-accent-text uppercase">
                            Operación diaria
                        </p>
                        <h1 className="mt-2 text-3xl font-semibold tracking-tight text-admin-text-primary">
                            Panel
                        </h1>
                        <p className="mt-1 max-w-2xl text-sm text-admin-text-tertiary">
                            Revisá qué necesita atención hoy y cómo viene la
                            operación.
                        </p>
                    </div>
                    <div className="flex flex-col gap-3 sm:flex-row sm:items-center">
                        <PeriodSelector
                            value={dashboard.period}
                            onChange={selectPeriod}
                        />
                        <Button asChild className="h-12 rounded-xl px-5">
                            <Link href="/admin">
                                <Plus aria-hidden="true" /> Nuevo pedido
                            </Link>
                        </Button>
                    </div>
                </header>

                <div className="grid min-w-0 gap-4 xl:grid-cols-12">
                    <div className="order-1 min-w-0 xl:order-2 xl:col-span-8">
                        <UrgentOrders
                            orders={dashboard.urgent_orders}
                            onOpenOrder={openOrder}
                        />
                    </div>
                    <div className="order-2 min-w-0 xl:order-4 xl:col-span-8">
                        <TodayOrders
                            orders={dashboard.today_orders}
                            onOpenOrder={openOrder}
                        />
                    </div>
                    <div className="order-3 min-w-0 xl:order-1 xl:col-span-12">
                        <DashboardKpis dashboard={dashboard} />
                    </div>
                    <div className="order-4 min-w-0 xl:order-3 xl:col-span-4">
                        <OperationalStatus
                            statuses={dashboard.today_status_distribution}
                            empanadasCount={dashboard.kpis.empanadas_count}
                        />
                    </div>
                    <div className="order-5 min-w-0 xl:order-5 xl:col-span-4">
                        <TopProducts products={dashboard.top_products} />
                    </div>
                    <div className="order-6 min-w-0 xl:col-span-8">
                        <SalesEvolutionChart
                            period={dashboard.period}
                            points={dashboard.evolution}
                        />
                    </div>
                    <div className="order-7 grid min-w-0 content-start gap-4 xl:col-span-4">
                        <DashboardMarketingSummary summary={marketingSummary} />
                        <OrderDistributions dashboard={dashboard} />
                    </div>
                </div>
            </section>

            <OrderDetailSheet
                order={selectedOrder}
                summary={selectedOrderSummary}
                onClose={closeOrder}
            />
        </>
    );
}
