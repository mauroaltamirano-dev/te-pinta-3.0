import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetTitle,
} from '@/components/ui/sheet';
import { OrderDetailContent } from '@/features/orders/components/order-detail-content';
import type { AdminOrder } from '@/features/orders/types';

export function OrderDetailSheet({
    order,
    summary,
    onClose,
}: {
    order: AdminOrder | null;
    summary: string | null;
    onClose: () => void;
}) {
    return (
        <Sheet
            open={order !== null}
            onOpenChange={(open) => !open && onClose()}
        >
            <SheetContent
                data-testid="order-detail-sheet"
                side="right"
                overlayClassName="bg-black/70"
                className="dark admin-shell w-full max-w-none gap-0 overflow-y-auto border-admin-border bg-admin-canvas p-0 sm:max-w-2xl [&_[aria-live=polite]]:bg-transparent [&_[aria-live=polite]]:text-white [&_[aria-live=polite]]:hover:bg-admin-action [&_[aria-live=polite]]:hover:text-white [&>button]:text-white"
            >
                <SheetTitle className="sr-only">
                    {order
                        ? `Detalle del pedido ${order.id}`
                        : 'Detalle del pedido'}
                </SheetTitle>
                <SheetDescription className="sr-only">
                    Detalle operativo del pedido con acciones de estado y pago.
                </SheetDescription>
                {order && (
                    <div className="px-4 py-12 sm:px-6 sm:py-8">
                        <OrderDetailContent
                            key={`${order.id}-${order.version ?? 0}`}
                            order={order}
                            summary={summary ?? ''}
                            compact
                            showFullDetailLink
                        />
                    </div>
                )}
            </SheetContent>
        </Sheet>
    );
}
