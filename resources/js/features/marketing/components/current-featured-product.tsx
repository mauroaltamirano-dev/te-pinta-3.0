import { ImageIcon, Pencil, Power } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';
import {
    featuredProductStatusLabels,
    featuredProductStatusStyles,
    formatMarketingDate,
    visibilityReasonLabels,
} from '../presentation';
import type { AdminFeaturedProduct } from '../types';

export function CurrentFeaturedProduct({
    featured,
    timezone,
    onCreate,
    onEdit,
    onDisable,
}: {
    featured: AdminFeaturedProduct | null;
    timezone: string;
    onCreate: () => void;
    onEdit: (featured: AdminFeaturedProduct) => void;
    onDisable: (featured: AdminFeaturedProduct) => void;
}) {
    return (
        <section
            data-testid="marketing-current"
            className="min-w-0 rounded-xl border border-admin-border bg-admin-surface p-4 sm:p-5"
        >
            <header>
                <p className="text-xs font-semibold tracking-[0.16em] text-admin-accent uppercase">
                    Recomendación actual
                </p>
                <h2 className="mt-2 text-xl font-semibold text-white">
                    Qué destacamos ahora
                </h2>
            </header>

            {!featured ? (
                <div className="mt-5 grid min-h-64 place-items-center rounded-xl border border-dashed border-admin-border bg-admin-canvas px-5 py-10 text-center">
                    <div>
                        <div className="mx-auto grid size-12 place-items-center rounded-xl bg-admin-selection text-admin-accent">
                            <ImageIcon className="size-6" aria-hidden="true" />
                        </div>
                        <h3 className="mt-4 text-lg font-semibold text-white">
                            No hay una recomendación activa
                        </h3>
                        <p className="mx-auto mt-2 max-w-sm text-sm text-admin-text-secondary">
                            Destacá un producto para mostrarlo en la tienda
                            pública.
                        </p>
                        <Button className="mt-5" onClick={onCreate}>
                            Crear recomendación
                        </Button>
                    </div>
                </div>
            ) : (
                <div className="mt-5 grid gap-5 sm:grid-cols-[8rem_minmax(0,1fr)]">
                    <ProductImage featured={featured} />
                    <div className="min-w-0">
                        <div className="flex flex-wrap items-center gap-2">
                            <span
                                className={cn(
                                    'inline-flex rounded-full border px-2.5 py-1 text-xs font-semibold',
                                    featuredProductStatusStyles[
                                        featured.status
                                    ],
                                )}
                            >
                                {featuredProductStatusLabels[featured.status]}
                            </span>
                            {!featured.is_publicly_visible && (
                                <span className="inline-flex rounded-full border border-amber-400/25 bg-amber-400/10 px-2.5 py-1 text-xs font-semibold text-amber-200">
                                    No visible en la tienda
                                </span>
                            )}
                        </div>
                        <h3 className="mt-3 truncate text-2xl font-semibold text-white">
                            {featured.public_view.product.name}
                        </h3>
                        {featured.headline && (
                            <p className="mt-1 text-sm font-medium text-admin-text-secondary">
                                {featured.headline}
                            </p>
                        )}
                        <dl className="mt-4 grid gap-3 text-sm sm:grid-cols-2">
                            <div>
                                <dt className="text-xs text-admin-text-tertiary">
                                    Inicio
                                </dt>
                                <dd className="mt-1 text-admin-text-secondary">
                                    {formatMarketingDate(
                                        featured.starts_at,
                                        timezone,
                                    )}
                                </dd>
                            </div>
                            <div>
                                <dt className="text-xs text-admin-text-tertiary">
                                    Finalización
                                </dt>
                                <dd className="mt-1 text-admin-text-secondary">
                                    {formatMarketingDate(
                                        featured.ends_at,
                                        timezone,
                                    )}
                                </dd>
                            </div>
                        </dl>
                        {featured.visibility_reason && (
                            <p className="mt-4 rounded-lg border border-amber-400/20 bg-amber-400/10 px-3 py-2 text-sm text-amber-100">
                                {
                                    visibilityReasonLabels[
                                        featured.visibility_reason
                                    ]
                                }{' '}
                                Actualmente no se muestra en la tienda pública.
                            </p>
                        )}
                        <div className="mt-5 flex flex-wrap gap-2">
                            <Button
                                type="button"
                                variant="outline"
                                onClick={() => onEdit(featured)}
                            >
                                <Pencil aria-hidden="true" /> Editar
                            </Button>
                            <Button
                                type="button"
                                variant="ghost"
                                className="text-admin-text-secondary hover:text-white"
                                onClick={() => onDisable(featured)}
                            >
                                <Power aria-hidden="true" /> Desactivar
                            </Button>
                        </div>
                    </div>
                </div>
            )}
        </section>
    );
}

function ProductImage({ featured }: { featured: AdminFeaturedProduct }) {
    return (
        <div className="mx-auto size-28 overflow-hidden rounded-xl bg-admin-canvas sm:mx-0 sm:aspect-square sm:size-auto">
            {featured.public_view.product.image_url ? (
                <img
                    src={featured.public_view.product.image_url}
                    alt={featured.public_view.product.name}
                    className="size-full object-cover"
                />
            ) : (
                <div className="grid size-full place-items-center text-admin-text-tertiary">
                    <ImageIcon className="size-8" aria-hidden="true" />
                    <span className="sr-only">Sin imagen del producto</span>
                </div>
            )}
        </div>
    );
}
