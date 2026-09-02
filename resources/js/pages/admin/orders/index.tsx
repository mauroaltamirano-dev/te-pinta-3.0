import { Head, Link, router } from '@inertiajs/react';
import {
    ArchiveRestore,
    Bookmark,
    CalendarDays,
    ChevronLeft,
    ChevronRight,
    CircleDollarSign,
    Filter,
    Grid2X2,
    List,
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
    Sheet,
    SheetContent,
    SheetDescription,
    SheetFooter,
    SheetHeader,
    SheetTitle,
    SheetTrigger,
} from '@/components/ui/sheet';
import { ToggleGroup, ToggleGroupItem } from '@/components/ui/toggle-group';
import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { OrderCollection } from '@/features/orders/components/order-collection';
import { OrderTicketDialog } from '@/features/orders/components/order-ticket-dialog';
import { OrdersInsightsPanel } from '@/features/orders/components/orders-insights-panel';
import {
    formatOrderMoney,
    orderStatusLabels,
    orderStatuses,
} from '@/features/orders/presentation';
import type {
    AdminOrder,
    OrderInsightsCondition,
    OrderInsightsDeliveryType,
    OrderInsightsProduct,
    OrdersView,
    OrderStatus,
    PaginatedOrders,
} from '@/features/orders/types';

const orderGroups = [
    { value: 'all', label: 'Todos' },
    { value: 'in_progress', label: 'En curso' },
    { value: 'delivered', label: 'Entregados' },
    { value: 'cancelled', label: 'Cancelados' },
] as const;

type OrderGroup = (typeof orderGroups)[number]['value'];
type OrderSort = 'delivery_date' | 'customer' | 'total' | 'id';
type SortDirection = 'asc' | 'desc';

interface OrdersProps {
    orders: PaginatedOrders;
    filters: {
        search: string;
        status: OrderStatus | null;
        group: OrderGroup;
        date: string | null;
        sort: OrderSort;
        direction: SortDirection;
        view: OrdersView;
    };
    selectedOrder?: AdminOrder | null;
    selectedOrderSummary?: string | null;
    mostOrderedProducts: OrderInsightsProduct[];
    deliveryTypeDistribution: OrderInsightsDeliveryType[];
    conditionDistribution: OrderInsightsCondition[];
}

function groupForStatus(
    status: OrderStatus | 'all',
    fallback: OrderGroup,
): OrderGroup {
    if (status === 'delivered' || status === 'cancelled') {
        return status;
    }

    if (status !== 'all') {
        return 'in_progress';
    }

    return fallback;
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
    const [group, setGroup] = useState<OrderGroup>(filters.group);
    const [date, setDate] = useState(filters.date ?? '');
    const [sort, setSort] = useState<OrderSort>(filters.sort);
    const [direction, setDirection] = useState<SortDirection>(
        filters.direction,
    );
    const [view, setView] = useState<OrdersView>(filters.view);
    const [filterOpen, setFilterOpen] = useState(false);
    const visiblePending = orders.data.filter((order) =>
        ['pending_confirmation', 'waiting', 'prepared'].includes(order.status),
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

    const navigationParams = (
        overrides: Partial<{
            search: string;
            status: OrderStatus | 'all';
            group: OrderGroup;
            date: string;
            sort: OrderSort;
            direction: SortDirection;
            view: OrdersView;
            order: string;
        }> = {},
    ) => {
        const next = {
            search,
            status,
            group,
            date,
            sort,
            direction,
            view,
            ...overrides,
        };

        return {
            ...(next.search ? { search: next.search } : {}),
            ...(next.status !== 'all' ? { status: next.status } : {}),
            ...(next.group !== 'all' ? { group: next.group } : {}),
            ...(next.date ? { date: next.date } : {}),
            ...(next.sort !== 'delivery_date' ? { sort: next.sort } : {}),
            ...(next.direction !== 'desc' ? { direction: next.direction } : {}),
            ...(next.view !== 'grid' ? { view: next.view } : {}),
            ...(next.order ? { order: next.order } : {}),
        };
    };
    const navigate = (
        overrides: Parameters<typeof navigationParams>[0] = {},
        preserveScroll = false,
    ) =>
        router.get('/admin/orders', navigationParams(overrides), {
            preserveState: true,
            preserveScroll,
            replace: true,
        });
    const submitSearch = (event: FormEvent) => {
        event.preventDefault();
        navigate();
    };
    const applyFilters = (event: FormEvent) => {
        event.preventDefault();
        const nextGroup = groupForStatus(status, group);
        setGroup(nextGroup);
        setFilterOpen(false);
        navigate({ group: nextGroup });
    };
    const clearFilters = () => {
        setStatus('all');
        setDate('');
        setSort('delivery_date');
        setDirection('desc');
        setFilterOpen(false);
        navigate({
            status: 'all',
            date: '',
            sort: 'delivery_date',
            direction: 'desc',
        });
    };
    const selectGroup = (nextGroup: OrderGroup) => {
        setGroup(nextGroup);
        setStatus('all');
        navigate({ group: nextGroup, status: 'all' });
    };
    const selectView = (nextView: OrdersView) => {
        setView(nextView);
        navigate({ view: nextView }, true);
    };
    const openOrder = (order: AdminOrder) =>
        router.get('/admin/orders', navigationParams({ order: order.id }), {
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
            <section className="font-orders xl:h-full xl:min-h-0">
                <div
                    data-testid="orders-dashboard-grid"
                    className="grid min-w-0 items-start gap-6 xl:h-full xl:min-h-0 xl:grid-cols-[minmax(0,646fr)_minmax(372px,372fr)]"
                >
                    <div
                        data-testid="orders-primary-column"
                        className="grid min-w-0 content-start gap-4 xl:h-full xl:min-h-0 xl:grid-rows-[auto_auto_auto_auto_minmax(0,1fr)]"
                    >
                        <header className="grid gap-4 lg:grid-cols-[minmax(12rem,1fr)_auto] lg:items-end">
                            <div>
                                <p className="text-sm font-medium text-admin-accent">
                                    Gestión de pedidos
                                </p>
                                <h1 className="mt-1 text-3xl font-semibold text-white">
                                    Pedidos
                                </h1>
                                <p className="mt-1 text-sm text-admin-text-tertiary">
                                    Revisá entregas, pagos y estados desde un
                                    solo lugar.
                                </p>
                            </div>
                            <div className="grid gap-2 sm:grid-cols-[minmax(15rem,1fr)_auto_auto]">
                                <form
                                    role="search"
                                    onSubmit={submitSearch}
                                    className="flex min-w-0 gap-2"
                                >
                                    <div className="relative min-w-0 flex-1">
                                        <Search className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-admin-text-tertiary" />
                                        <Input
                                            aria-label="Buscar pedidos o clientes"
                                            value={search}
                                            onChange={(event) =>
                                                setSearch(event.target.value)
                                            }
                                            placeholder="Pedido o cliente"
                                            className="h-11 border-admin-border bg-admin-surface pl-9"
                                        />
                                    </div>
                                    <Button
                                        type="submit"
                                        variant="outline"
                                        size="icon"
                                        className="size-11 shrink-0"
                                        aria-label="Buscar pedidos"
                                    >
                                        <Search />
                                    </Button>
                                </form>
                                <Button
                                    asChild
                                    variant="outline"
                                    className="h-11 rounded-lg px-4"
                                >
                                    <Link href="/admin/orders/trash">
                                        <ArchiveRestore /> Papelera
                                    </Link>
                                </Button>
                                <Button
                                    asChild
                                    className="h-11 rounded-lg px-4"
                                >
                                    <Link href="/admin/orders/create">
                                        <Plus /> Nuevo pedido
                                    </Link>
                                </Button>
                            </div>
                        </header>

                        <div className="grid gap-3 sm:grid-cols-3">
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
                                label="En curso visibles"
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

                        <div className="flex min-w-0 items-center justify-between gap-3">
                            <nav
                                aria-label="Grupos de pedidos"
                                className="min-w-0 overflow-x-auto rounded-lg border border-admin-border bg-admin-surface p-1"
                            >
                                <div className="flex min-w-max gap-1 pr-3">
                                    {orderGroups.map((item) => (
                                        <button
                                            key={item.value}
                                            type="button"
                                            aria-current={
                                                group === item.value
                                                    ? 'page'
                                                    : undefined
                                            }
                                            onClick={() =>
                                                selectGroup(item.value)
                                            }
                                            className={`min-h-11 rounded-md px-4 text-sm font-semibold whitespace-nowrap focus-visible:ring-2 focus-visible:ring-admin-accent focus-visible:outline-none ${
                                                group === item.value
                                                    ? 'bg-admin-accent text-white'
                                                    : 'text-admin-text-secondary hover:text-white'
                                            }`}
                                        >
                                            {item.label}
                                        </button>
                                    ))}
                                </div>
                            </nav>

                            <div className="flex shrink-0 items-center gap-2">
                                <Sheet
                                    open={filterOpen}
                                    onOpenChange={setFilterOpen}
                                >
                                    <Tooltip>
                                        <TooltipTrigger asChild>
                                            <SheetTrigger asChild>
                                                <Button
                                                    type="button"
                                                    variant="outline"
                                                    size="icon"
                                                    className="size-11"
                                                    aria-label="Abrir filtros y orden"
                                                >
                                                    <Filter />
                                                </Button>
                                            </SheetTrigger>
                                        </TooltipTrigger>
                                        <TooltipContent>
                                            Filtros y orden
                                        </TooltipContent>
                                    </Tooltip>
                                    <SheetContent
                                        className="admin-shell dark border-admin-border bg-admin-surface"
                                        overlayClassName="bg-black/70"
                                    >
                                        <SheetHeader>
                                            <SheetTitle className="text-white">
                                                Filtros y orden
                                            </SheetTitle>
                                            <SheetDescription className="text-admin-text-secondary">
                                                Afiná el estado, la fecha y el
                                                orden del listado.
                                            </SheetDescription>
                                        </SheetHeader>
                                        <form
                                            id="orders-filter-form"
                                            onSubmit={applyFilters}
                                            className="grid gap-5 px-4"
                                        >
                                            <label className="grid gap-2 text-sm font-medium text-white">
                                                Estado exacto
                                                <Select
                                                    value={status}
                                                    onValueChange={(value) =>
                                                        setStatus(
                                                            value as
                                                                | OrderStatus
                                                                | 'all',
                                                        )
                                                    }
                                                >
                                                    <SelectTrigger
                                                        aria-label="Filtrar por estado exacto"
                                                        className="h-11 bg-admin-canvas"
                                                    >
                                                        <SelectValue />
                                                    </SelectTrigger>
                                                    <SelectContent>
                                                        <SelectItem value="all">
                                                            Todos los estados
                                                            del grupo
                                                        </SelectItem>
                                                        {orderStatuses.map(
                                                            (value) => (
                                                                <SelectItem
                                                                    key={value}
                                                                    value={
                                                                        value
                                                                    }
                                                                >
                                                                    {
                                                                        orderStatusLabels[
                                                                            value
                                                                        ]
                                                                    }
                                                                </SelectItem>
                                                            ),
                                                        )}
                                                    </SelectContent>
                                                </Select>
                                            </label>
                                            <label className="grid gap-2 text-sm font-medium text-white">
                                                Fecha de entrega
                                                <div className="relative">
                                                    <CalendarDays className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-admin-text-tertiary" />
                                                    <Input
                                                        type="date"
                                                        aria-label="Filtrar por fecha de entrega"
                                                        value={date}
                                                        onChange={(event) =>
                                                            setDate(
                                                                event.target
                                                                    .value,
                                                            )
                                                        }
                                                        className="h-11 bg-admin-canvas pl-9"
                                                    />
                                                </div>
                                            </label>
                                            <label className="grid gap-2 text-sm font-medium text-white">
                                                Ordenar por
                                                <Select
                                                    value={sort}
                                                    onValueChange={(value) =>
                                                        setSort(
                                                            value as OrderSort,
                                                        )
                                                    }
                                                >
                                                    <SelectTrigger
                                                        aria-label="Ordenar pedidos por"
                                                        className="h-11 bg-admin-canvas"
                                                    >
                                                        <SelectValue />
                                                    </SelectTrigger>
                                                    <SelectContent>
                                                        <SelectItem value="delivery_date">
                                                            Fecha de entrega
                                                        </SelectItem>
                                                        <SelectItem value="customer">
                                                            Cliente
                                                        </SelectItem>
                                                        <SelectItem value="total">
                                                            Total
                                                        </SelectItem>
                                                        <SelectItem value="id">
                                                            ID de pedido
                                                        </SelectItem>
                                                    </SelectContent>
                                                </Select>
                                            </label>
                                            <label className="grid gap-2 text-sm font-medium text-white">
                                                Dirección
                                                <Select
                                                    value={direction}
                                                    onValueChange={(value) =>
                                                        setDirection(
                                                            value as SortDirection,
                                                        )
                                                    }
                                                >
                                                    <SelectTrigger
                                                        aria-label="Dirección del orden"
                                                        className="h-11 bg-admin-canvas"
                                                    >
                                                        <SelectValue />
                                                    </SelectTrigger>
                                                    <SelectContent>
                                                        <SelectItem value="desc">
                                                            Descendente
                                                        </SelectItem>
                                                        <SelectItem value="asc">
                                                            Ascendente
                                                        </SelectItem>
                                                    </SelectContent>
                                                </Select>
                                            </label>
                                        </form>
                                        <SheetFooter>
                                            <Button
                                                type="button"
                                                variant="ghost"
                                                onClick={clearFilters}
                                                className="h-11 text-admin-text-secondary"
                                            >
                                                Limpiar filtros
                                            </Button>
                                            <Button
                                                type="submit"
                                                form="orders-filter-form"
                                                className="h-11"
                                            >
                                                Aplicar filtros
                                            </Button>
                                        </SheetFooter>
                                    </SheetContent>
                                </Sheet>

                                <ToggleGroup
                                    type="single"
                                    value={view}
                                    onValueChange={(value) =>
                                        value && selectView(value as OrdersView)
                                    }
                                    variant="outline"
                                    aria-label="Vista de pedidos"
                                    data-testid="orders-view-toggle"
                                    className="border-admin-border bg-admin-surface"
                                >
                                    <ToggleGroupItem
                                        value="grid"
                                        aria-label="Vista en grilla"
                                        className="size-11 p-0 data-[state=on]:bg-admin-accent data-[state=on]:text-white"
                                    >
                                        <Grid2X2 />
                                    </ToggleGroupItem>
                                    <ToggleGroupItem
                                        value="list"
                                        aria-label="Vista en lista"
                                        className="size-11 p-0 data-[state=on]:bg-admin-accent data-[state=on]:text-white"
                                    >
                                        <List />
                                    </ToggleGroupItem>
                                </ToggleGroup>
                            </div>
                        </div>

                        <p className="text-sm text-admin-text-tertiary">
                            {orders.total}{' '}
                            {orders.total === 1 ? 'pedido' : 'pedidos'}
                        </p>

                        <div
                            data-testid="orders-results-scroll"
                            className="min-h-0 xl:overflow-x-hidden xl:overflow-y-auto xl:overscroll-contain xl:pr-1"
                        >
                            {orders.data.length === 0 ? (
                                <p
                                    role="status"
                                    className="rounded-lg border border-admin-border bg-admin-surface px-6 py-16 text-center text-admin-text-tertiary"
                                >
                                    No hay pedidos para estos filtros.
                                </p>
                            ) : (
                                <OrderCollection
                                    orders={orders.data}
                                    view={view}
                                    onOpen={openOrder}
                                />
                            )}

                            {orders.last_page > 1 && (
                                <nav
                                    aria-label="Paginación de pedidos"
                                    className="mt-4 flex items-center justify-between rounded-lg border border-admin-border bg-admin-surface px-4 py-3"
                                >
                                    <p className="text-sm text-admin-text-tertiary">
                                        Página {orders.current_page} de{' '}
                                        {orders.last_page}
                                    </p>
                                    <div className="flex gap-2">
                                        <Button
                                            variant="outline"
                                            size="icon"
                                            aria-label="Página anterior"
                                            disabled={!orders.prev_page_url}
                                            asChild={Boolean(
                                                orders.prev_page_url,
                                            )}
                                        >
                                            {orders.prev_page_url ? (
                                                <Link
                                                    href={orders.prev_page_url}
                                                >
                                                    <ChevronLeft />
                                                </Link>
                                            ) : (
                                                <span>
                                                    <ChevronLeft />
                                                </span>
                                            )}
                                        </Button>
                                        <Button
                                            variant="outline"
                                            size="icon"
                                            aria-label="Página siguiente"
                                            disabled={!orders.next_page_url}
                                            asChild={Boolean(
                                                orders.next_page_url,
                                            )}
                                        >
                                            {orders.next_page_url ? (
                                                <Link
                                                    href={orders.next_page_url}
                                                >
                                                    <ChevronRight />
                                                </Link>
                                            ) : (
                                                <span>
                                                    <ChevronRight />
                                                </span>
                                            )}
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
            className="grid min-h-24 grid-cols-[auto_1fr] items-center gap-x-3 rounded-lg bg-admin-surface p-3"
        >
            <div className="flex size-9 items-center justify-center rounded-lg bg-admin-canvas">
                <Icon className={`size-5 ${tones[tone]}`} strokeWidth={1.7} />
            </div>
            <div className="flex min-w-0 items-center justify-between gap-2">
                <p className="truncate text-xl font-semibold whitespace-nowrap text-white tabular-nums">
                    {value}
                </p>
                <span
                    className={`flex items-center gap-1 text-xs font-medium ${positive ? 'text-[#50d1aa]' : 'text-[#ff7ca3]'}`}
                >
                    {trend}
                    <TrendIcon className="size-3.5" strokeWidth={2} />
                </span>
            </div>
            <p className="col-start-2 text-xs font-medium text-admin-text-secondary">
                {label}
            </p>
        </div>
    );
}
