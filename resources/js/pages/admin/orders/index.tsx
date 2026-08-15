import { Head, Link, router } from '@inertiajs/react';
import {
    CalendarDays,
    ArchiveRestore,
    Bookmark,
    ChevronLeft,
    ChevronRight,
    CircleDollarSign,
    CircleUserRound,
    Plus,
    Search,
    TrendingDown,
    TrendingUp,
    UsersRound,
} from 'lucide-react';
import { useState } from 'react';
import type { FormEvent } from 'react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import {
    OrderPaymentToggle,
    OrderStatusSelect,
} from '@/features/orders/components/order-quick-controls';
import { OrderTicketDialog } from '@/features/orders/components/order-ticket-dialog';
import { OrdersInsightsPanel } from '@/features/orders/components/orders-insights-panel';
import {
    deliveryPeriodLabels,
    deliveryTypeLabels,
    formatOrderMoney,
    orderStatusLabels,
    orderStatuses,
} from '@/features/orders/presentation';
import type {
    AdminOrder,
    OrderInsightsCondition,
    OrderInsightsDeliveryType,
    OrderInsightsProduct,
    OrderStatus,
    PaginatedOrders,
} from '@/features/orders/types';

interface OrdersProps {
    orders: PaginatedOrders;
    filters: {
        search: string;
        status: OrderStatus | null;
        date: string | null;
    };
    selectedOrder?: AdminOrder | null;
    selectedOrderSummary?: string | null;
    mostOrderedProducts: OrderInsightsProduct[];
    deliveryTypeDistribution: OrderInsightsDeliveryType[];
    conditionDistribution: OrderInsightsCondition[];
}

export default function Orders({
    orders,
    filters,
    selectedOrder = null,
    selectedOrderSummary = null,
    mostOrderedProducts,
    deliveryTypeDistribution,
    conditionDistribution,
}: OrdersProps) {
    const [search, setSearch] = useState(filters.search);
    const [status, setStatus] = useState<OrderStatus | 'all'>(
        filters.status ?? 'all',
    );
    const [date, setDate] = useState(filters.date ?? '');
    const visiblePending = orders.data.filter((order) =>
        ['pending_confirmation', 'waiting'].includes(order.status),
    ).length;
    const visiblePaidTotal = orders.data
        .filter((order) => order.payment_status === 'paid')
        .reduce((total, order) => total + order.total, 0);
    const visibleOrders = orders.data.length;
    const visiblePaid = orders.data.filter(
        (order) => order.payment_status === 'paid',
    ).length;
    const visibleDelivered = orders.data.filter(
        (order) => order.status === 'delivered',
    ).length;
    const percentage = (value: number) =>
        visibleOrders === 0 ? 0 : Math.round((value / visibleOrders) * 100);

    const navigationParams = (order?: string) => ({
        ...(search ? { search } : {}),
        ...(status !== 'all' ? { status } : {}),
        ...(date ? { date } : {}),
        ...(order ? { order } : {}),
    });
    const submit = (event: FormEvent) => {
        event.preventDefault();
        router.get('/admin/orders', navigationParams(), {
            preserveState: true,
            replace: true,
        });
    };
    const openOrder = (order: AdminOrder) =>
        router.get('/admin/orders', navigationParams(order.id), {
            preserveState: true,
            preserveScroll: true,
            only: ['selectedOrder', 'selectedOrderSummary'],
        });
    const closeOrder = () =>
        router.get('/admin/orders', navigationParams(), {
            preserveState: true,
            preserveScroll: true,
            only: ['selectedOrder', 'selectedOrderSummary'],
        });

    return (
        <>
            <Head title="Pedidos" />
            <section className="font-orders xl:h-[calc(100vh-3rem)] xl:overflow-hidden">
                <div
                    data-testid="orders-dashboard-grid"
                    className="grid min-w-0 gap-6 xl:h-full xl:w-full xl:grid-cols-[minmax(0,646fr)_minmax(372px,372fr)]"
                >
                    <div
                        data-testid="orders-primary-column"
                        className="grid min-w-0 content-start gap-6 xl:min-h-0 xl:grid-rows-[auto_143px_minmax(0,1fr)]"
                    >
                        <header className="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                            <div>
                                <p className="text-sm font-medium text-admin-accent">
                                    Gestión de pedidos
                                </p>
                                <h1 className="mt-1 text-3xl font-semibold tracking-tight text-white">
                                    Pedidos
                                </h1>
                                <p className="mt-1 text-sm text-admin-text-tertiary">
                                    Revisá entregas, pagos y estados desde un
                                    solo lugar.
                                </p>
                            </div>
                            <div className="flex flex-wrap gap-2">
                                <Button
                                    asChild
                                    variant="outline"
                                    className="h-12 rounded-lg px-5"
                                >
                                    <Link href="/admin/orders/trash">
                                        <ArchiveRestore /> Papelera
                                    </Link>
                                </Button>
                                <Button
                                    asChild
                                    className="h-12 rounded-lg px-5"
                                >
                                    <Link href="/admin/orders/create">
                                        <Plus /> Nuevo pedido
                                    </Link>
                                </Button>
                            </div>
                        </header>

                        <div className="grid gap-4 sm:grid-cols-3 sm:gap-6">
                            <SummaryCard
                                icon={CircleDollarSign}
                                value={formatOrderMoney.format(
                                    visiblePaidTotal,
                                )}
                                label="Cobrado en esta página"
                                tone="accent"
                                trend={`${percentage(visiblePaid)}%`}
                                trendDirection="up"
                            />
                            <SummaryCard
                                icon={Bookmark}
                                value={visiblePending.toString()}
                                label="Pendientes visibles"
                                tone="warning"
                                trend={`${percentage(visiblePending)}%`}
                                trendDirection="down"
                            />
                            <SummaryCard
                                icon={UsersRound}
                                value={orders.total.toString()}
                                label="Pedidos encontrados"
                                tone="info"
                                trend={`${percentage(visibleDelivered)}%`}
                                trendDirection="up"
                            />
                        </div>

                        <div
                            data-testid="orders-report"
                            className="flex min-h-0 flex-col overflow-hidden rounded-lg bg-admin-surface"
                        >
                            <div className="flex flex-col gap-4 border-b border-border px-4 py-5 sm:px-6">
                                <div className="flex items-center justify-between gap-3">
                                    <div>
                                        <h2 className="text-xl font-semibold text-white">
                                            Reporte de pedidos
                                        </h2>
                                        <p className="mt-1 text-sm text-admin-text-tertiary">
                                            {orders.total}{' '}
                                            {orders.total === 1
                                                ? 'pedido'
                                                : 'pedidos'}
                                        </p>
                                    </div>
                                </div>
                                <form
                                    onSubmit={submit}
                                    role="search"
                                    className="grid gap-3 md:grid-cols-2 2xl:grid-cols-[minmax(15rem,1fr)_13rem_12rem_auto]"
                                >
                                    <div className="relative">
                                        <Search className="absolute top-1/2 left-3 size-4 -translate-y-1/2 text-admin-text-tertiary" />
                                        <Input
                                            aria-label="Buscar pedidos o clientes"
                                            value={search}
                                            onChange={(event) =>
                                                setSearch(event.target.value)
                                            }
                                            placeholder="ID del pedido o cliente"
                                            className="h-11 border-border bg-admin-surface pl-9"
                                        />
                                    </div>
                                    <Select
                                        value={status}
                                        onValueChange={(value) =>
                                            setStatus(
                                                value as OrderStatus | 'all',
                                            )
                                        }
                                    >
                                        <SelectTrigger
                                            aria-label="Filtrar por estado"
                                            className="h-11 bg-admin-surface"
                                        >
                                            <SelectValue />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="all">
                                                Todos los estados
                                            </SelectItem>
                                            {orderStatuses.map((value) => (
                                                <SelectItem
                                                    key={value}
                                                    value={value}
                                                >
                                                    {orderStatusLabels[value]}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                    <div className="relative">
                                        <CalendarDays className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-admin-text-tertiary" />
                                        <Input
                                            type="date"
                                            aria-label="Filtrar por fecha de entrega"
                                            value={date}
                                            onChange={(event) =>
                                                setDate(event.target.value)
                                            }
                                            className="h-11 bg-admin-surface pl-9"
                                        />
                                    </div>
                                    <Button
                                        type="submit"
                                        variant="outline"
                                        className="h-11"
                                    >
                                        Aplicar
                                    </Button>
                                </form>
                            </div>

                            {orders.data.length === 0 ? (
                                <p
                                    role="status"
                                    className="px-6 py-20 text-center text-admin-text-tertiary"
                                >
                                    No hay pedidos para estos filtros.
                                </p>
                            ) : (
                                <div
                                    data-testid="orders-report-scroll"
                                    className="min-h-0 flex-1 overflow-auto"
                                >
                                    <table className="w-full min-w-[680px] text-left text-sm">
                                        <thead
                                            data-testid="orders-report-head"
                                            className="sticky top-0 z-10 bg-admin-surface text-xs text-admin-text-tertiary"
                                        >
                                            <tr>
                                                {[
                                                    'Pedido',
                                                    'Cliente',
                                                    'Entrega',
                                                    'Total',
                                                    'Estado',
                                                    'Pago',
                                                ].map((heading) => (
                                                    <th
                                                        key={heading}
                                                        scope="col"
                                                        className="px-4 py-4 font-medium 2xl:px-6"
                                                    >
                                                        {heading}
                                                    </th>
                                                ))}
                                            </tr>
                                        </thead>
                                        <tbody className="divide-y divide-border">
                                            {orders.data.map((order) => (
                                                <tr
                                                    key={order.id}
                                                    className="transition-colors hover:bg-white/[0.025]"
                                                >
                                                    <td className="px-4 py-5 font-medium 2xl:px-6">
                                                        <button
                                                            type="button"
                                                            onClick={() =>
                                                                openOrder(order)
                                                            }
                                                            className="text-admin-accent underline-offset-4 hover:underline focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-admin-accent"
                                                        >
                                                            {order.id}
                                                        </button>
                                                    </td>
                                                    <td className="px-4 py-4 2xl:px-6">
                                                        <div className="flex items-center gap-4">
                                                            <span
                                                                className="flex size-8 shrink-0 items-center justify-center rounded-full bg-[#ffb572] text-admin-surface"
                                                                aria-hidden="true"
                                                            >
                                                                <CircleUserRound
                                                                    className="size-5"
                                                                    strokeWidth={
                                                                        1.8
                                                                    }
                                                                />
                                                            </span>
                                                            <Link
                                                                href={`/admin/customers/${order.customer.id}`}
                                                                className="font-normal text-[#e0e6e9] underline-offset-4 hover:underline"
                                                            >
                                                                {
                                                                    order
                                                                        .customer
                                                                        .name
                                                                }
                                                            </Link>
                                                        </div>
                                                    </td>
                                                    <td className="px-4 py-5 2xl:px-6">
                                                        <p className="text-white">
                                                            {
                                                                deliveryTypeLabels[
                                                                    order
                                                                        .delivery_type
                                                                ]
                                                            }
                                                        </p>
                                                        <p className="mt-1 text-xs text-admin-text-tertiary">
                                                            {
                                                                order.delivery_date
                                                            }{' '}
                                                            ·{' '}
                                                            {
                                                                deliveryPeriodLabels[
                                                                    order
                                                                        .delivery_period
                                                                ]
                                                            }
                                                        </p>
                                                    </td>
                                                    <td className="px-4 py-5 font-medium text-white tabular-nums 2xl:px-6">
                                                        {formatOrderMoney.format(
                                                            order.total,
                                                        )}
                                                    </td>
                                                    <td className="px-4 py-4 2xl:px-6">
                                                        <OrderStatusSelect
                                                            order={order}
                                                        />
                                                    </td>
                                                    <td className="px-4 py-5 2xl:px-6">
                                                        <OrderPaymentToggle
                                                            order={order}
                                                        />
                                                    </td>
                                                </tr>
                                            ))}
                                        </tbody>
                                    </table>
                                </div>
                            )}

                            {orders.last_page > 1 && (
                                <nav
                                    aria-label="Paginación de pedidos"
                                    className="flex items-center justify-between border-t border-border px-4 py-4 sm:px-6"
                                >
                                    <p className="text-sm text-admin-text-tertiary">
                                        Página {orders.current_page} de{' '}
                                        {orders.last_page}
                                    </p>
                                    <div className="flex gap-2">
                                        <Button
                                            asChild
                                            variant="outline"
                                            size="icon"
                                            disabled={!orders.prev_page_url}
                                        >
                                            <Link
                                                href={
                                                    orders.prev_page_url ?? '#'
                                                }
                                                aria-label="Página anterior"
                                            >
                                                <ChevronLeft />
                                            </Link>
                                        </Button>
                                        <Button
                                            asChild
                                            variant="outline"
                                            size="icon"
                                            disabled={!orders.next_page_url}
                                        >
                                            <Link
                                                href={
                                                    orders.next_page_url ?? '#'
                                                }
                                                aria-label="Página siguiente"
                                            >
                                                <ChevronRight />
                                            </Link>
                                        </Button>
                                    </div>
                                </nav>
                            )}
                        </div>
                    </div>

                    <OrdersInsightsPanel
                        topProducts={mostOrderedProducts}
                        deliveryTypes={deliveryTypeDistribution}
                        conditions={conditionDistribution}
                        onViewAll={() => router.get('/admin/products')}
                    />
                </div>
            </section>

            <OrderTicketDialog
                order={selectedOrder}
                summary={selectedOrderSummary}
                onClose={closeOrder}
            />
        </>
    );
}

function SummaryCard({
    icon: Icon,
    value,
    label,
    tone,
    trend,
    trendDirection,
}: {
    icon: typeof CircleDollarSign;
    value: string;
    label: string;
    tone: 'accent' | 'warning' | 'info';
    trend: string;
    trendDirection: 'up' | 'down';
}) {
    const tones = {
        accent: 'text-[#9288e0]',
        warning: 'text-[#ffb572]',
        info: 'text-[#65b0f6]',
    } as const;
    const positive = trendDirection === 'up';
    const TrendIcon = positive ? TrendingUp : TrendingDown;

    return (
        <div
            data-testid="order-summary-card"
            className="flex h-[143px] w-full flex-col items-start gap-2 rounded-lg bg-admin-surface p-4"
        >
            <div className="flex items-center gap-3">
                <div className="flex size-[38px] items-center justify-center rounded-lg bg-admin-canvas">
                    <Icon
                        className={`size-6 ${tones[tone]}`}
                        strokeWidth={1.7}
                    />
                </div>
                <div className="flex items-center gap-1.5">
                    <span
                        className={`text-xs leading-[1.4] font-medium ${positive ? 'text-[#50d1aa]' : 'text-[#ff7ca3]'}`}
                    >
                        {trend}
                    </span>
                    <span
                        className={`flex size-[18px] items-center justify-center rounded-full ${positive ? 'bg-[rgba(136,224,145,0.24)] text-[#50d1aa]' : 'bg-[rgba(255,100,113,0.24)] text-[#ff7ca3]'}`}
                    >
                        <TrendIcon className="size-3.5" strokeWidth={2} />
                    </span>
                </div>
            </div>
            <div className="flex flex-col items-start gap-2">
                <p className="text-[28px] leading-[1.4] font-semibold whitespace-nowrap text-white tabular-nums">
                    {value}
                </p>
                <p className="text-sm leading-[1.3] font-medium text-admin-text-secondary">
                    {label}
                </p>
            </div>
        </div>
    );
}
