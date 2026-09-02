import type { AdminOrder, OrdersView } from '@/features/orders/types';
import { cn } from '@/lib/utils';
import { OrderCard } from './order-card';

interface OrderCollectionProps {
    orders: AdminOrder[];
    view: OrdersView;
    onOpen: (order: AdminOrder) => void;
}

export function OrderCollection({
    orders,
    view,
    onOpen,
}: OrderCollectionProps) {
    return (
        <div
            data-testid="orders-card-grid"
            data-view={view}
            className={cn(
                'grid min-w-0 items-stretch gap-4',
                view === 'grid' &&
                    'grid-cols-1 min-[640px]:grid-cols-[repeat(auto-fit,minmax(18rem,1fr))]',
                view === 'list' && 'grid-cols-1 gap-2',
            )}
        >
            {orders.map((order) => (
                <OrderCard
                    key={order.id}
                    order={order}
                    view={view}
                    onOpen={onOpen}
                />
            ))}
        </div>
    );
}
