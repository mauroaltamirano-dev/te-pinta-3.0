import { Plus } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { ProductImagePlaceholder } from '@/features/products/components/product-image-placeholder';
import { formatMoney } from '@/lib/format';
import { cn } from '@/lib/utils';
import type { FeaturedProductPublicView } from '../types';

export function FeaturedProductCard({
    featured,
    onAddProduct,
    className,
}: {
    featured: FeaturedProductPublicView;
    onAddProduct?: (slug: string) => void;
    className?: string;
}) {
    const copy = featured.short_copy ?? featured.product.description;

    return (
        <article
            data-testid="featured-product-card"
            className={cn(
                'public-theme @container overflow-hidden rounded-[2rem] border border-border bg-card text-card-foreground',
                className,
            )}
        >
            <div className="grid @3xl:grid-cols-[minmax(0,1fr)_minmax(0,1fr)]">
                <div className="relative aspect-[4/3] min-h-56 overflow-hidden bg-secondary @3xl:aspect-auto @3xl:min-h-96">
                    {featured.product.image_url ? (
                        <img
                            src={featured.product.image_url}
                            alt={featured.product.name}
                            className="size-full object-cover"
                            loading="lazy"
                        />
                    ) : (
                        <ProductImagePlaceholder />
                    )}
                </div>
                <div className="flex min-w-0 flex-col justify-center p-6 @xl:p-8 @3xl:p-12">
                    <p className="text-xs font-bold tracking-[0.2em] text-public-earth-ink uppercase">
                        Recomendación de la casa
                    </p>
                    {featured.headline && (
                        <p className="mt-6 max-w-xl text-lg font-semibold text-muted-foreground">
                            {featured.headline}
                        </p>
                    )}
                    <h3 className="mt-2 font-serif text-4xl leading-[1] font-semibold tracking-tight text-balance @xl:text-5xl">
                        {featured.product.name}
                    </h3>
                    {copy && (
                        <p className="mt-5 max-w-xl text-sm leading-6 text-muted-foreground @xl:text-base">
                            {copy}
                        </p>
                    )}
                    <div className="mt-8 flex flex-wrap items-end justify-between gap-5 border-t border-border pt-6">
                        <div>
                            <p className="text-xs font-semibold tracking-wide text-muted-foreground uppercase">
                                {featured.product.pricing_family === 'empanada'
                                    ? 'Docena cocinada'
                                    : 'Precio por unidad'}
                            </p>
                            <p className="mt-1 text-2xl font-bold tabular-nums">
                                {formatMoney(
                                    featured.product.pricing_family ===
                                        'empanada'
                                        ? featured.product.prices.dozen!
                                        : featured.product.prices.unit,
                                )}
                            </p>
                        </div>
                        <Button
                            type="button"
                            size="lg"
                            className="min-h-11 rounded-full px-6 disabled:bg-public-ink/60 disabled:text-white disabled:opacity-100"
                            disabled={!onAddProduct}
                            aria-label={`Agregar una unidad de ${featured.product.name} al carrito`}
                            onClick={() =>
                                onAddProduct?.(featured.product.slug)
                            }
                        >
                            <Plus aria-hidden="true" /> Agregar al carrito
                        </Button>
                    </div>
                </div>
            </div>
        </article>
    );
}
