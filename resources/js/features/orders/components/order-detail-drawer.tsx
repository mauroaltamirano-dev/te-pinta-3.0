import {
    Sheet,
    SheetContent,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet';
import { OrderDetailContent } from '@/features/orders/components/order-detail-content';
import type { AdminOrder } from '@/features/orders/types';

export function OrderDetailDrawer({
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
                aria-describedby={undefined}
                className="w-full gap-0 overflow-hidden border-admin-surface-raised bg-admin-surface p-0 font-orders sm:max-w-[32rem] lg:max-w-[36rem]"
            >
                {order && (
                    <>
                        <SheetHeader className="border-b border-border px-5 py-5 sm:px-6">
                            <p className="text-xs font-medium tracking-[0.14em] text-admin-accent uppercase">
                                Detalle del pedido
                            </p>
                            <SheetTitle className="text-2xl text-white">
                                {order.id}
                            </SheetTitle>
                        </SheetHeader>
                        <div className="min-h-0 flex-1 overflow-y-auto px-4 py-5 sm:px-6">
                            <OrderDetailContent
                                order={order}
                                summary={summary ?? ''}
                                compact
                                showFullDetailLink
                            />
                        </div>
                    </>
                )}
            </SheetContent>
        </Sheet>
    );
}
