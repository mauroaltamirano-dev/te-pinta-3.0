import type {
    FeaturedProductStatus,
    FeaturedProductVisibilityReason,
} from './types';

export const featuredProductStatusLabels: Record<
    FeaturedProductStatus,
    string
> = {
    inactive: 'Inactiva',
    scheduled: 'Programada',
    active: 'Activa',
    finished: 'Finalizada',
};

export const featuredProductStatusStyles: Record<
    FeaturedProductStatus,
    string
> = {
    inactive: 'border-white/10 bg-white/5 text-admin-text-secondary',
    scheduled: 'border-sky-400/20 bg-sky-400/10 text-sky-200',
    active: 'border-emerald-400/20 bg-emerald-400/10 text-emerald-200',
    finished: 'border-white/10 bg-white/5 text-admin-text-tertiary',
};

export const visibilityReasonLabels: Record<
    Exclude<FeaturedProductVisibilityReason, null>,
    string
> = {
    product_unavailable: 'El producto no está disponible.',
    product_deleted: 'El producto fue eliminado.',
    category_unavailable: 'La categoría del producto no está disponible.',
};

export function formatMarketingDate(
    value: string | null,
    timezone: string,
): string {
    if (!value) {
        return 'Sin fecha de finalización';
    }

    const date = new Date(value);

    if (Number.isNaN(date.getTime())) {
        return value;
    }

    return new Intl.DateTimeFormat('es-AR', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
        timeZone: timezone,
    }).format(date);
}
