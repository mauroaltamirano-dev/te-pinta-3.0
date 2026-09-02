import { ImageIcon, Plus } from 'lucide-react';
import type { CSSProperties } from 'react';
import { Button } from '@/components/ui/button';
import { formatPesos } from '@/features/products/utils';
import { cn } from '@/lib/utils';
import type { FeaturedProductPublicView } from '../types';

const publicTheme = {
    '--background': 'oklch(0.985 0.008 88)',
    '--foreground': 'oklch(0.22 0.03 28)',
    '--card': 'oklch(0.998 0.003 88)',
    '--card-foreground': 'oklch(0.22 0.03 28)',
    '--primary': 'oklch(0.54 0.18 31)',
    '--primary-foreground': 'oklch(0.985 0.008 88)',
    '--secondary': 'oklch(0.9 0.06 82)',
    '--secondary-foreground': 'oklch(0.26 0.04 30)',
    '--muted': 'oklch(0.94 0.018 85)',
    '--muted-foreground': 'oklch(0.48 0.035 42)',
    '--accent': 'oklch(0.76 0.1 126)',
    '--accent-foreground': 'oklch(0.22 0.04 126)',
    '--border': 'oklch(0.87 0.026 78)',
    '--ring': 'oklch(0.54 0.18 31)',
    '--color-background': 'oklch(0.985 0.008 88)',
    '--color-foreground': 'oklch(0.22 0.03 28)',
    '--color-card': 'oklch(0.998 0.003 88)',
    '--color-card-foreground': 'oklch(0.22 0.03 28)',
    '--color-primary': 'oklch(0.54 0.18 31)',
    '--color-primary-foreground': 'oklch(0.985 0.008 88)',
    '--color-muted': 'oklch(0.94 0.018 85)',
    '--color-muted-foreground': 'oklch(0.48 0.035 42)',
    '--color-secondary': 'oklch(0.9 0.06 82)',
    '--color-secondary-foreground': 'oklch(0.26 0.04 30)',
    '--color-accent': 'oklch(0.76 0.1 126)',
    '--color-accent-foreground': 'oklch(0.22 0.04 126)',
    '--color-border': 'oklch(0.87 0.026 78)',
    '--color-ring': 'oklch(0.54 0.18 31)',
} as CSSProperties;

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
            style={publicTheme}
            className={cn(
                '@container overflow-hidden rounded-[1.75rem] border border-border bg-card text-card-foreground shadow-sm',
                className,
            )}
        >
            <div className="grid @3xl:grid-cols-[minmax(0,0.9fr)_minmax(0,1.1fr)]">
                <div className="relative aspect-[4/3] min-h-56 overflow-hidden bg-muted @3xl:aspect-auto @3xl:min-h-80">
                    {featured.product.image_url ? (
                        <img
                            src={featured.product.image_url}
                            alt={featured.product.name}
                            className="size-full object-cover"
                        />
                    ) : (
                        <div className="food-mark grid size-full place-items-center">
                            <div className="grid size-20 place-items-center rounded-full bg-background/75 text-foreground shadow-lg backdrop-blur-sm">
                                <ImageIcon
                                    className="size-9"
                                    aria-hidden="true"
                                />
                                <span className="sr-only">
                                    Sin imagen del producto
                                </span>
                            </div>
                        </div>
                    )}
                </div>

                <div className="flex min-w-0 flex-col justify-center p-6 @xl:p-8 @3xl:p-10">
                    <p className="text-xs font-bold tracking-[0.2em] text-primary uppercase">
                        Recomendación de la casa
                    </p>
                    {featured.headline && (
                        <p className="mt-4 max-w-xl text-lg font-semibold text-muted-foreground">
                            {featured.headline}
                        </p>
                    )}
                    <h2 className="mt-2 font-display text-4xl leading-none font-black tracking-tight text-balance @xl:text-5xl">
                        {featured.product.name}
                    </h2>
                    {copy && (
                        <p className="mt-4 max-w-xl text-sm leading-6 text-muted-foreground @xl:text-base">
                            {copy}
                        </p>
                    )}
                    <div className="mt-6 flex flex-wrap items-center justify-between gap-4 border-t border-border pt-5">
                        <div>
                            <p className="text-xs font-medium tracking-wide text-muted-foreground uppercase">
                                Desde · docena cocinada
                            </p>
                            <p className="mt-1 text-2xl font-bold tabular-nums">
                                {formatPesos(featured.product.prices.dozen)}
                            </p>
                        </div>
                        <Button
                            type="button"
                            size="lg"
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
