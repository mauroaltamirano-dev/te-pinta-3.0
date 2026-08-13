import { ImageIcon } from 'lucide-react';
import { useMemo, useState } from 'react';
import type { AdminProductOption } from '../admin-order-types';
import type { AdminOrderDraft } from '../hooks/use-admin-order-draft';
import { formatOrderMoney } from '../presentation';

export function QuickOrderCatalog({
    products,
    draft,
    today,
}: {
    products: AdminProductOption[];
    draft: AdminOrderDraft;
    today: string;
}) {
    const categories = useMemo(
        () =>
            Array.from(
                new Map(
                    products.map((product) => [
                        product.category.id,
                        product.category,
                    ]),
                ).values(),
            ),
        [products],
    );
    const [activeCategory, setActiveCategory] = useState<number | 'all'>('all');
    const visibleProducts =
        activeCategory === 'all'
            ? products
            : products.filter(
                  (product) => product.category.id === activeCategory,
              );

    return (
        <section className="min-w-0 p-5 sm:p-6 lg:h-screen lg:overflow-y-auto">
            <header className="mb-6">
                <p className="text-sm text-admin-text-secondary">
                    {new Intl.DateTimeFormat('es-AR', {
                        weekday: 'long',
                        day: 'numeric',
                        month: 'long',
                    }).format(new Date(`${today}T12:00:00`))}
                </p>
                <h1 className="mt-1 text-2xl font-semibold">Nuevo pedido</h1>
            </header>

            <nav
                aria-label="Categorías de productos"
                className="mb-6 flex gap-6 overflow-x-auto border-b border-admin-border"
            >
                <button
                    type="button"
                    aria-pressed={activeCategory === 'all'}
                    onClick={() => setActiveCategory('all')}
                    className={`shrink-0 border-b-2 px-0.5 pb-3 text-sm font-medium ${
                        activeCategory === 'all'
                            ? 'border-admin-accent text-admin-accent'
                            : 'border-transparent text-admin-text-secondary'
                    }`}
                >
                    Todas
                </button>
                {categories.map((category) => (
                    <button
                        key={category.id}
                        type="button"
                        aria-pressed={activeCategory === category.id}
                        onClick={() => setActiveCategory(category.id)}
                        className={`shrink-0 border-b-2 px-0.5 pb-3 text-sm font-medium ${
                            activeCategory === category.id
                                ? 'border-admin-accent text-admin-accent'
                                : 'border-transparent text-admin-text-secondary'
                        }`}
                    >
                        {category.name}
                    </button>
                ))}
            </nav>

            <div className="mb-7 flex items-end justify-between gap-4">
                <div>
                    <h2 className="text-xl font-semibold">
                        Elegí las variedades
                    </h2>
                    <p className="mt-1 text-sm text-admin-text-secondary">
                        Sumá por docena, media docena o unidad.
                    </p>
                </div>
                <span className="shrink-0 text-sm text-admin-text-secondary">
                    {visibleProducts.length} disponibles
                </span>
            </div>

            {products.length === 0 ? (
                <div className="grid min-h-64 place-items-center rounded-2xl border border-dashed border-admin-border text-center text-admin-text-secondary">
                    No hay productos disponibles para vender.
                </div>
            ) : (
                <div className="grid gap-x-7 gap-y-10 sm:grid-cols-2 min-[74.625rem]:grid-cols-3">
                    {visibleProducts.map((product) => {
                        const quantity =
                            draft.form.data.items.find(
                                (line) => line.product_id === product.id,
                            )?.quantity ?? 0;

                        return (
                            <article
                                key={product.id}
                                className="relative flex h-[16.25rem] flex-col items-center rounded-2xl bg-admin-surface px-4 pt-16 pb-5 text-center"
                            >
                                <span className="absolute -top-[1.125rem] grid size-[8.25rem] place-items-center overflow-hidden rounded-full bg-admin-surface-raised ring-4 ring-admin-canvas">
                                    {product.image_url ? (
                                        <img
                                            src={product.image_url}
                                            alt=""
                                            className="size-full object-cover"
                                        />
                                    ) : (
                                        <ImageIcon
                                            className="size-12 text-admin-text-tertiary"
                                            strokeWidth={1.4}
                                        />
                                    )}
                                </span>
                                <span className="mt-14 line-clamp-2 min-h-10 font-medium">
                                    {product.name}
                                </span>
                                <span className="mt-2 text-sm text-admin-text-secondary">
                                    Desde{' '}
                                    {formatOrderMoney.format(
                                        product.unit_price,
                                    )}
                                </span>
                                <span className="mt-1 text-xs text-admin-text-tertiary">
                                    {product.category.name}
                                </span>
                                <div
                                    className="mt-auto grid w-full grid-cols-3 gap-2 pt-3"
                                    aria-label={`Agregar ${product.name} al pedido`}
                                >
                                    {[12, 6, 1].map((increment) => (
                                        <button
                                            key={increment}
                                            type="button"
                                            aria-label={`Agregar ${increment} ${increment === 1 ? 'unidad' : 'unidades'} de ${product.name}`}
                                            onClick={() =>
                                                draft.changeProduct(
                                                    product.id,
                                                    increment,
                                                )
                                            }
                                            className="rounded-lg border border-admin-border py-1.5 text-sm font-semibold text-admin-accent transition-colors hover:border-admin-accent hover:bg-admin-accent/10 focus-visible:ring-2 focus-visible:ring-admin-accent focus-visible:outline-none"
                                        >
                                            {increment}
                                        </button>
                                    ))}
                                </div>
                                {quantity > 0 && (
                                    <span className="absolute top-3 right-3 grid size-8 place-items-center rounded-full bg-admin-accent font-semibold text-white shadow-admin-nav-active">
                                        {quantity}
                                    </span>
                                )}
                            </article>
                        );
                    })}
                </div>
            )}
        </section>
    );
}
