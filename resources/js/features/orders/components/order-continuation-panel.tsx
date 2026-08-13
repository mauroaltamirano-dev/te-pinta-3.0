import { ArrowLeft, CheckCircle2 } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Sheet, SheetContent, SheetTitle } from '@/components/ui/sheet';
import type {
    AdminAddonOption,
    AdminCustomerOption,
} from '../admin-order-types';
import type { AdminOrderDraft } from '../hooks/use-admin-order-draft';
import { formatOrderMoney } from '../presentation';
import { AdminOrderDetailsFields } from './admin-order-details-fields';
import { PriceSummary } from './price-summary';

export function OrderContinuationPanel({
    open,
    onClose,
    draft,
    customers,
    addons,
}: {
    open: boolean;
    onClose: () => void;
    draft: AdminOrderDraft;
    customers: AdminCustomerOption[];
    addons: AdminAddonOption[];
}) {
    const panelOpen = open || draft.createdOrder !== null;
    const closePanel = () => {
        draft.clearCreatedOrder();
        onClose();
    };

    return (
        <Sheet
            open={panelOpen}
            onOpenChange={(nextOpen) => {
                if (!nextOpen) {
                    closePanel();
                }
            }}
        >
            <SheetContent
                side="right"
                overlayClassName="bg-black/70"
                className="admin-shell dark w-full max-w-none gap-0 overflow-hidden border-admin-border bg-admin-surface p-0 text-admin-text-primary sm:max-w-[51rem]"
            >
                {draft.createdOrder ? (
                    <div className="grid h-full place-items-center p-8 text-center">
                        <div className="max-w-md">
                            <CheckCircle2 className="mx-auto size-14 text-emerald-400" />
                            <p className="mt-5 text-sm text-admin-text-secondary">
                                Pedido confirmado
                            </p>
                            <SheetTitle className="mt-1 text-3xl text-admin-text-primary">
                                {draft.createdOrder.order_id}
                            </SheetTitle>
                            <p className="mt-3 text-lg">
                                Total{' '}
                                {formatOrderMoney.format(
                                    draft.createdOrder.total,
                                )}
                            </p>
                            <Button
                                type="button"
                                size="lg"
                                className="mt-8"
                                onClick={closePanel}
                            >
                                Crear otro pedido
                            </Button>
                        </div>
                    </div>
                ) : (
                    <form
                        className="flex min-h-0 flex-1 flex-col"
                        onSubmit={async (event) => {
                            event.preventDefault();
                            await draft.submitQuickOrder();
                        }}
                    >
                        <header className="border-b border-admin-border px-5 py-4 pr-14 sm:px-6 sm:pr-14">
                            <button
                                type="button"
                                onClick={onClose}
                                className="mb-2 inline-flex items-center gap-2 text-sm text-admin-text-secondary hover:text-white"
                            >
                                <ArrowLeft className="size-4" /> Volver al
                                catálogo
                            </button>
                            <SheetTitle className="text-2xl text-admin-text-primary">
                                Completar pedido
                            </SheetTitle>
                        </header>

                        <div className="min-h-0 flex-1 overflow-y-auto p-5 sm:p-6">
                            <AdminOrderDetailsFields
                                draft={draft}
                                customers={customers}
                                addons={addons}
                            />
                            <div className="mt-6">
                                <PriceSummary
                                    quote={draft.quote}
                                    loading={draft.quoteLoading}
                                    compact
                                />
                            </div>
                            {Object.keys(draft.form.errors).length > 0 && (
                                <p
                                    role="alert"
                                    className="mt-4 text-sm text-red-300"
                                >
                                    {draft.form.errors.items ??
                                        'Revisá los datos del pedido e intentá nuevamente.'}
                                </p>
                            )}
                        </div>

                        <footer className="grid gap-3 border-t border-admin-border bg-admin-surface px-5 py-4 sm:grid-cols-[1fr_1.2fr] sm:px-6">
                            <Button
                                type="button"
                                variant="outline"
                                onClick={onClose}
                            >
                                Cancelar
                            </Button>
                            <Button
                                type="submit"
                                disabled={
                                    draft.submittingQuickOrder ||
                                    draft.quoteLoading ||
                                    !draft.quote
                                }
                            >
                                {draft.submittingQuickOrder
                                    ? 'Confirmando…'
                                    : 'Confirmar pedido'}
                            </Button>
                        </footer>
                    </form>
                )}
            </SheetContent>
        </Sheet>
    );
}
