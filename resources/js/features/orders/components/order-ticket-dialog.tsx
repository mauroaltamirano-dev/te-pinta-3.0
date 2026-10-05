import { ReceiptText } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogTitle,
} from '@/components/ui/dialog';
import { CopyOrderDetailButton } from '@/features/orders/components/copy-order-detail-button';
import { OrderEditButton } from '@/features/orders/components/order-edit-button';
import {
    formatDeliveryPeriod,
    formatOrderCustomer,
    deliveryTypeLabels,
    formatOrderDate,
    formatOrderMoney,
    orderConditionLabels,
    orderStatusLabels,
    paymentMethodLabels,
    paymentStatusLabels,
    pricingModeLabels,
} from '@/features/orders/presentation';
import type { AdminOrder } from '@/features/orders/types';

export function OrderTicketDialog({
    order,
    summary,
    onClose,
}: {
    order: AdminOrder | null;
    summary: string | null;
    onClose: () => void;
}) {
    const closed =
        order?.status === 'delivered' || order?.status === 'cancelled';

    return (
        <Dialog
            open={order !== null}
            onOpenChange={(open) => !open && onClose()}
        >
            <DialogContent
                data-testid="order-ticket-dialog"
                className="max-h-[calc(100dvh-2rem)] gap-0 overflow-hidden rounded-sm border-0 bg-ticket-paper p-0 font-orders text-ticket-ink shadow-2xl sm:max-w-[34rem]"
            >
                {order && (
                    <>
                        <DialogTitle className="sr-only">
                            Pedido {order.id}
                        </DialogTitle>
                        <DialogDescription className="sr-only">
                            Comprobante resumido del pedido
                        </DialogDescription>
                        <div className="min-h-0 overflow-y-auto px-5 pt-7 pb-5 sm:px-8 sm:pt-8">
                            <header className="border-b border-dashed border-ticket-rule pb-5 text-center">
                                <span className="mx-auto grid size-10 place-items-center rounded-full bg-admin-accent text-white">
                                    <ReceiptText className="size-5" />
                                </span>
                                <p className="mt-3 text-[0.6875rem] font-semibold tracking-[0.18em] text-ticket-muted uppercase">
                                    Comprobante de pedido
                                </p>
                                <h2 className="mt-1 text-2xl font-bold tracking-tight">
                                    {order.id}
                                </h2>
                                <div className="mt-3 flex flex-wrap justify-center gap-2">
                                    <Badge className="border-0 bg-admin-accent/15 text-[#a74534] shadow-none">
                                        {orderStatusLabels[order.status]}
                                    </Badge>
                                    <Badge
                                        className={
                                            order.payment_status === 'paid'
                                                ? 'border-0 bg-[#dceee5] text-[#287153] shadow-none'
                                                : order.payment_status ===
                                                    'not_required'
                                                  ? 'border-0 bg-[#dceaf2] text-[#28647a] shadow-none'
                                                  : 'border-0 bg-[#f6e0dc] text-[#9b493c] shadow-none'
                                        }
                                    >
                                        {
                                            paymentStatusLabels[
                                                order.payment_status
                                            ]
                                        }
                                        {order.payment_method
                                            ? ` · ${paymentMethodLabels[order.payment_method]}`
                                            : ''}
                                    </Badge>
                                </div>
                            </header>

                            <dl className="grid grid-cols-[auto_1fr] gap-x-5 gap-y-2 border-b border-dashed border-ticket-rule py-5 text-sm">
                                <dt className="text-ticket-muted">Cliente</dt>
                                <dd className="text-right font-semibold">
                                    {formatOrderCustomer(order)}
                                </dd>
                                <dt className="text-ticket-muted">Entrega</dt>
                                <dd className="text-right font-medium">
                                    {formatOrderDate(order.delivery_date)} ·{' '}
                                    {formatDeliveryPeriod(
                                        order.delivery_period,
                                    )}
                                </dd>
                                <dt className="text-ticket-muted">Modalidad</dt>
                                <dd className="text-right font-medium">
                                    {deliveryTypeLabels[order.delivery_type]}
                                </dd>
                                <dt className="text-ticket-muted">Condición</dt>
                                <dd className="text-right font-medium">
                                    {orderConditionLabels[order.condition]}
                                </dd>
                            </dl>

                            <section className="border-b border-dashed border-ticket-rule py-5">
                                <h3 className="mb-4 text-xs font-bold tracking-[0.14em] uppercase">
                                    Detalle
                                </h3>
                                <div className="grid gap-4">
                                    {order.items?.map((item, index) => (
                                        <div
                                            key={`${item.pricing_mode}-${index}`}
                                            className="grid grid-cols-[1fr_auto] gap-x-4 text-sm"
                                        >
                                            <div>
                                                <p className="font-semibold">
                                                    {item.quantity} ×{' '}
                                                    {pricingModeLabels[
                                                        item.pricing_mode
                                                    ] ?? item.pricing_mode}
                                                    {item.product_name
                                                        ? ` · ${item.product_name}`
                                                        : ''}
                                                </p>
                                                {item.composition.length >
                                                    0 && (
                                                    <ul className="mt-1.5 grid gap-1 text-xs text-ticket-muted">
                                                        {item.composition.map(
                                                            (part) => (
                                                                <li
                                                                    key={`${part.product_name}-${part.quantity}`}
                                                                >
                                                                    └{' '}
                                                                    {
                                                                        part.quantity
                                                                    }{' '}
                                                                    ×{' '}
                                                                    {
                                                                        part.product_name
                                                                    }
                                                                </li>
                                                            ),
                                                        )}
                                                    </ul>
                                                )}
                                            </div>
                                            <span className="font-semibold tabular-nums">
                                                {formatOrderMoney.format(
                                                    item.subtotal,
                                                )}
                                            </span>
                                        </div>
                                    ))}
                                </div>
                            </section>

                            <section className="pt-5">
                                <div className="flex items-end justify-between gap-5">
                                    <span className="text-sm font-semibold">
                                        Total
                                    </span>
                                    <span className="text-2xl font-bold tabular-nums">
                                        {formatOrderMoney.format(order.total)}
                                    </span>
                                </div>
                            </section>
                        </div>

                        <footer className="grid grid-cols-2 gap-3 border-t border-ticket-rule bg-ticket-paper px-5 py-4 sm:px-8">
                            <CopyOrderDetailButton
                                summary={summary ?? ''}
                                className="h-11 border-ticket-rule bg-ticket-paper text-ticket-ink hover:bg-ticket-rule/35 hover:text-ticket-ink"
                            />
                            <OrderEditButton
                                orderId={order.id}
                                version={order.version}
                                closed={closed}
                                compactLabel
                                className="h-11 border-admin-accent bg-admin-accent text-white hover:bg-[#d96d5b] hover:text-white"
                            />
                        </footer>
                    </>
                )}
            </DialogContent>
        </Dialog>
    );
}
