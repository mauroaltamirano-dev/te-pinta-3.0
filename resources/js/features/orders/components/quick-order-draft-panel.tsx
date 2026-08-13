import { Minus, Plus, ShoppingBasket, Trash2, X } from 'lucide-react';
import { Button } from '@/components/ui/button';
import type { AdminProductOption } from '../admin-order-types';
import type { AdminOrderDraft } from '../hooks/use-admin-order-draft';
import { PriceSummary } from './price-summary';

export function QuickOrderDraftPanel({
    products,
    draft,
    onContinue,
    onClose,
}: {
    products: AdminProductOption[];
    draft: AdminOrderDraft;
    onContinue: () => void;
    onClose?: () => void;
}) {
    const selectedProducts = draft.form.data.items.flatMap((line) => {
        const product = products.find((item) => item.id === line.product_id);

        return product ? [{ ...line, product }] : [];
    });

    return (
        <aside className="flex min-h-[34rem] flex-col rounded-lg bg-admin-surface p-5 lg:sticky lg:top-0 lg:h-screen lg:min-h-0 lg:rounded-none lg:rounded-l-lg lg:p-6">
            <header className="flex items-start justify-between gap-4 border-b border-admin-border pb-5">
                <div>
                    <p className="text-xs font-semibold tracking-[0.16em] text-admin-accent uppercase">
                        Pedido actual
                    </p>
                    <h2 className="mt-1 text-xl font-semibold">Tu selección</h2>
                </div>
                {onClose && (
                    <Button
                        type="button"
                        size="icon"
                        variant="ghost"
                        aria-label="Cerrar pedido actual"
                        onClick={onClose}
                    >
                        <X />
                    </Button>
                )}
            </header>

            <div className="min-h-0 flex-1 overflow-y-auto py-4 pe-1">
                {selectedProducts.length === 0 ? (
                    <div className="grid min-h-52 place-items-center text-center text-admin-text-secondary">
                        <div>
                            <ShoppingBasket
                                className="mx-auto mb-3 size-9 text-admin-text-tertiary"
                                strokeWidth={1.4}
                            />
                            <p className="font-medium text-admin-text-primary">
                                El pedido está vacío
                            </p>
                            <p className="mt-1 text-sm">
                                Tocá una variedad para empezar.
                            </p>
                        </div>
                    </div>
                ) : (
                    <ul className="grid gap-3">
                        {selectedProducts.map(({ product, quantity }) => (
                            <li
                                key={product.id}
                                className="grid grid-cols-[minmax(0,1fr)_auto] items-center gap-3 rounded-lg bg-admin-surface-raised p-3"
                            >
                                <div className="flex min-w-0 items-center gap-3">
                                    <span className="size-10 shrink-0 overflow-hidden rounded-full bg-admin-surface">
                                        {product.image_url && (
                                            <img
                                                src={product.image_url}
                                                alt=""
                                                className="size-full object-cover"
                                            />
                                        )}
                                    </span>
                                    <div className="min-w-0">
                                        <p className="truncate font-medium">
                                            {product.name}
                                        </p>
                                        <p className="text-xs text-admin-text-secondary">
                                            {product.category.name}
                                        </p>
                                    </div>
                                </div>
                                <div className="flex items-center gap-1">
                                    <Button
                                        type="button"
                                        size="icon"
                                        variant="ghost"
                                        aria-label={`Quitar una unidad de ${product.name}`}
                                        onClick={() =>
                                            draft.changeProduct(product.id, -1)
                                        }
                                        className="size-8"
                                    >
                                        <Minus />
                                    </Button>
                                    <output
                                        aria-label={`Cantidad de ${product.name}: ${quantity}`}
                                        className="min-w-7 text-center font-medium tabular-nums"
                                    >
                                        {quantity}
                                    </output>
                                    <Button
                                        type="button"
                                        size="icon"
                                        variant="ghost"
                                        aria-label={`Agregar una unidad de ${product.name}`}
                                        onClick={() =>
                                            draft.changeProduct(product.id, 1)
                                        }
                                        className="size-8"
                                    >
                                        <Plus />
                                    </Button>
                                    <Button
                                        type="button"
                                        size="icon"
                                        variant="ghost"
                                        aria-label={`Eliminar ${product.name} del pedido`}
                                        onClick={() =>
                                            draft.removeProduct(product.id)
                                        }
                                        className="size-8 text-admin-accent"
                                    >
                                        <Trash2 />
                                    </Button>
                                </div>
                            </li>
                        ))}
                    </ul>
                )}

                <div className="mt-5">
                    <PriceSummary
                        quote={draft.quote}
                        loading={draft.quoteLoading}
                        compact
                    />
                </div>
                {draft.quoteError && (
                    <p role="alert" className="mt-3 text-sm text-red-300">
                        No pudimos calcular el precio. Intentá nuevamente.
                    </p>
                )}
            </div>

            <div className="grid gap-2 border-t border-admin-border pt-4">
                <Button
                    type="button"
                    size="lg"
                    onClick={onContinue}
                    disabled={
                        selectedProducts.length === 0 ||
                        draft.quoteLoading ||
                        !draft.quote
                    }
                    className="h-12 shadow-admin-nav-active"
                >
                    Continuar
                </Button>
                {selectedProducts.length > 0 && (
                    <Button
                        type="button"
                        variant="ghost"
                        onClick={draft.discard}
                        className="text-admin-text-secondary"
                    >
                        Descartar pedido
                    </Button>
                )}
            </div>
        </aside>
    );
}
