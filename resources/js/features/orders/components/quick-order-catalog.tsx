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
        <section className="min-w-0 px-3 py-4 sm:p-6 lg:h-screen lg:overflow-y-auto">
            <header className="mb-5 sm:mb-6">
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
                className="mb-5 flex gap-5 overflow-x-auto border-b border-admin-border sm:mb-6 sm:gap-6"
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

            <div className="mb-5 flex items-end justify-between gap-3 sm:mb-7 sm:gap-4">
                <div>
                    <h2 className="text-lg font-semibold sm:text-xl">
                        Elegí las variedades
                    </h2>
                    <p className="mt-1 text-sm text-admin-text-secondary">
                        Sumá por docena, media docena o unidad.
                    </p>
                </div>
                <span className="shrink-0 text-xs text-admin-text-secondary sm:text-sm">
                    {visibleProducts.length} disponibles
                </span>
            </div>

            {products.length === 0 ? (
                <div className="grid min-h-64 place-items-center rounded-2xl border border-dashed border-admin-border text-center text-admin-text-secondary">
                    No hay productos disponibles para vender.
                </div>
            ) : (
                <div className="grid grid-cols-2 gap-x-3 gap-y-7 sm:gap-x-7 sm:gap-y-10 min-[74.625rem]:grid-cols-3">
                    {visibleProducts.map((product) => {
                        const quantity =
                            draft.form.data.items.find(
                                (line) => line.product_id === product.id,
                            )?.quantity ?? 0;

                        return (
                            <article
                                key={product.id}
                                className="relative flex min-h-[13.5rem] flex-col items-center rounded-xl bg-admin-surface px-2 pt-11 pb-3 text-center sm:min-h-[18.5rem] sm:rounded-2xl sm:px-4 sm:pt-16 sm:pb-5"
                            >
                                <span className="absolute -top-3 grid size-[5.5rem] place-items-center overflow-hidden rounded-full bg-admin-surface-raised ring-[3px] ring-admin-canvas sm:-top-[1.125rem] sm:size-[8.25rem] sm:ring-4">
                                    {product.image_url ? (
                                        <img
                                            src={product.image_url}
                                            alt=""
                                            className="size-full object-cover"
                                        />
                                    ) : (
                                        <ImageIcon
                                            className="size-9 text-admin-text-tertiary sm:size-12"
                                            strokeWidth={1.4}
                                        />
                                    )}
                                </span>
                                <span className="mt-9 line-clamp-2 min-h-10 text-sm leading-tight font-medium sm:mt-14 sm:text-base sm:leading-normal">
                                    {product.name}
                                </span>
                                <span className="mt-1 text-admin-text-secondary sm:mt-2">
                                    <span className="block text-[0.6875rem] font-medium uppercase sm:text-xs">
                                        {product.pricing_family === 'empanada'
                                            ? 'Desde · docena cocinada'
                                            : 'Precio por unidad'}
                                    </span>
                                    <span className="mt-0.5 block text-sm font-semibold text-admin-text-primary tabular-nums">
                                        {formatOrderMoney.format(
                                            product.pricing_family ===
                                                'empanada'
                                                ? product.dozen_price
                                                : product.unit_price,
                                        )}
                                    </span>
                                </span>
                                <span className="mt-1 hidden text-xs text-admin-text-tertiary sm:block">
                                    {product.category.name}
                                </span>
                                <div
                                    className={`mt-auto grid w-full gap-1 pt-2 sm:gap-2 sm:pt-3 ${product.pricing_family === 'empanada' ? 'grid-cols-3' : 'grid-cols-1'}`}
                                    aria-label={`Agregar ${product.name} al pedido`}
                                >
                                    {(product.pricing_family === 'empanada'
                                        ? [12, 6, 1]
                                        : [1]
                                    ).map((increment) => (
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
                                            className="h-11 rounded-lg border border-admin-accent/50 bg-admin-action text-xs font-semibold text-admin-accent transition-[border-color,background-color,transform] hover:border-admin-accent hover:bg-admin-accent/35 focus-visible:ring-2 focus-visible:ring-admin-accent focus-visible:outline-none active:scale-[0.96] sm:text-sm"
                                        >
                                            {increment}
                                        </button>
                                    ))}
                                </div>
                                {quantity > 0 && (
                                    <span className="absolute top-2 right-2 grid size-7 place-items-center rounded-full bg-admin-accent text-sm font-semibold text-white shadow-admin-nav-active sm:top-3 sm:right-3 sm:size-8 sm:text-base">
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
