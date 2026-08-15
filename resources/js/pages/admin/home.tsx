import { Head } from '@inertiajs/react';
import { ShoppingBasket } from 'lucide-react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
import { Sheet, SheetContent } from '@/components/ui/sheet';
import type { AdminOrderOptions } from '@/features/orders/admin-order-types';
import { OrderContinuationPanel } from '@/features/orders/components/order-continuation-panel';
import { QuickOrderCatalog } from '@/features/orders/components/quick-order-catalog';
import { QuickOrderDraftPanel } from '@/features/orders/components/quick-order-draft-panel';
import { useAdminOrderDraft } from '@/features/orders/hooks/use-admin-order-draft';

export default function AdminHome({
    products,
    customers,
    addons,
    today,
}: AdminOrderOptions) {
    const draft = useAdminOrderDraft(customers, today);
    const [continuationOpen, setContinuationOpen] = useState(false);
    const [mobileCartOpen, setMobileCartOpen] = useState(false);
    const totalUnits = draft.form.data.items.reduce(
        (total, item) => total + item.quantity,
        0,
    );

    return (
        <>
            <Head title="Nuevo pedido" />
            <div className="font-orders lg:grid lg:h-screen lg:grid-cols-[minmax(0,1fr)_clamp(22.5rem,34.25vw,25.5625rem)]">
                <QuickOrderCatalog
                    products={products}
                    draft={draft}
                    today={today}
                />
                <div className="hidden lg:block">
                    <QuickOrderDraftPanel
                        products={products}
                        draft={draft}
                        onContinue={() => setContinuationOpen(true)}
                    />
                </div>
            </div>
            <Button
                type="button"
                size="lg"
                onClick={() => setMobileCartOpen(true)}
                className="fixed right-4 bottom-24 z-40 h-12 shadow-admin-nav-active lg:hidden"
            >
                <ShoppingBasket /> Pedido
                {totalUnits > 0 && (
                    <span className="rounded-full bg-white/20 px-2 py-0.5 tabular-nums">
                        {totalUnits}
                    </span>
                )}
            </Button>
            <Sheet open={mobileCartOpen} onOpenChange={setMobileCartOpen}>
                <SheetContent
                    side="bottom"
                    overlayClassName="bg-black/70"
                    className="admin-shell dark max-h-[90vh] gap-0 overflow-y-auto border-admin-border bg-admin-canvas p-3 lg:hidden"
                >
                    <QuickOrderDraftPanel
                        products={products}
                        draft={draft}
                        onContinue={() => {
                            setMobileCartOpen(false);
                            setContinuationOpen(true);
                        }}
                    />
                </SheetContent>
            </Sheet>
            <OrderContinuationPanel
                open={continuationOpen}
                onClose={() => setContinuationOpen(false)}
                draft={draft}
                customers={customers}
                addons={addons}
            />
        </>
    );
}
