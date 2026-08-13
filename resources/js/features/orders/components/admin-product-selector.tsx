import { Minus, Plus, Trash2 } from 'lucide-react';
import { Button } from '@/components/ui/button';
import type { AdminProductOption } from '../admin-order-types';
import type { AdminOrderDraft } from '../hooks/use-admin-order-draft';

export function AdminProductSelector({
    products,
    draft,
}: {
    products: AdminProductOption[];
    draft: AdminOrderDraft;
}) {
    return (
        <div className="grid gap-3 sm:grid-cols-2">
            {products.map((product) => {
                const quantity =
                    draft.form.data.items.find(
                        (item) => item.product_id === product.id,
                    )?.quantity ?? 0;

                return (
                    <article
                        key={product.id}
                        className="grid gap-3 rounded-lg border border-border p-3"
                    >
                        <div>
                            <p className="font-medium">{product.name}</p>
                            <p className="text-xs text-muted-foreground">
                                Cantidad: {quantity}
                            </p>
                        </div>
                        <div className="flex flex-wrap gap-2">
                            <Button
                                type="button"
                                size="sm"
                                aria-label={`Agregar una unidad de ${product.name}`}
                                onClick={() =>
                                    draft.changeProduct(product.id, 1)
                                }
                            >
                                <Plus /> Unidad
                            </Button>
                            <Button
                                type="button"
                                size="sm"
                                variant="outline"
                                aria-label={`Agregar media docena de ${product.name}`}
                                onClick={() =>
                                    draft.changeProduct(product.id, 6)
                                }
                            >
                                +6
                            </Button>
                            <Button
                                type="button"
                                size="sm"
                                variant="outline"
                                aria-label={`Agregar una docena de ${product.name}`}
                                onClick={() =>
                                    draft.changeProduct(product.id, 12)
                                }
                            >
                                +12
                            </Button>
                            {quantity > 0 && (
                                <>
                                    <Button
                                        type="button"
                                        size="icon"
                                        variant="ghost"
                                        aria-label={`Quitar una unidad de ${product.name}`}
                                        onClick={() =>
                                            draft.changeProduct(product.id, -1)
                                        }
                                    >
                                        <Minus />
                                    </Button>
                                    <Button
                                        type="button"
                                        size="icon"
                                        variant="ghost"
                                        aria-label={`Eliminar ${product.name} del pedido`}
                                        onClick={() =>
                                            draft.removeProduct(product.id)
                                        }
                                    >
                                        <Trash2 />
                                    </Button>
                                </>
                            )}
                        </div>
                    </article>
                );
            })}
        </div>
    );
}
