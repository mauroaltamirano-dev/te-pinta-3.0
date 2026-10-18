import { EyeOff } from 'lucide-react';
import type { AdminFeaturedProduct } from '../types';
import { FeaturedProductCard } from './featured-product-card';

export function MarketingPreview({
    featured,
}: {
    featured: AdminFeaturedProduct | null;
}) {
    return (
        <section
            data-testid="marketing-preview"
            className="min-w-0 rounded-xl border border-admin-border bg-admin-surface p-4 sm:p-5"
        >
            <header>
                <p className="text-xs font-semibold tracking-[0.16em] text-admin-accent-text uppercase">
                    Vista previa
                </p>
                <h2 className="mt-2 text-xl font-semibold text-admin-text-primary">
                    Así lo verá el cliente
                </h2>
            </header>
            {featured ? (
                <div className="mt-5 min-w-0 rounded-xl bg-admin-canvas p-3">
                    {!featured.is_publicly_visible && (
                        <p className="mb-3 flex items-center gap-2 rounded-lg border border-warning/40 bg-warning-subtle px-3 py-2 text-xs text-warning">
                            <EyeOff className="size-4" aria-hidden="true" />
                            Vista previa disponible, pero esta recomendación no
                            se publica actualmente.
                        </p>
                    )}
                    <FeaturedProductCard featured={featured.public_view} />
                </div>
            ) : (
                <div className="mt-5 grid min-h-64 place-items-center rounded-xl border border-dashed border-admin-border bg-admin-canvas px-6 text-center text-sm text-admin-text-secondary">
                    Cuando haya una recomendación activa o próxima, vas a verla
                    acá tal como aparecerá en la tienda.
                </div>
            )}
        </section>
    );
}
