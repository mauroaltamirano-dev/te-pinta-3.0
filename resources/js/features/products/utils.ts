/**
 * Display preview only; the backend stores the authoritative value
 * (App\\Modules\\Products\\Pricing\\EmpanadaUnitPrice): dozen / 12 × (1 + surcharge%),
 * rounded half-up to ARS 100.
 */
export function empanadaUnitPrice(
    dozenPrice: number,
    surchargePercent: number,
): number {
    const step = 1200 * 100;

    return (
        Math.floor((dozenPrice * (100 + surchargePercent) + step / 2) / step) *
        100
    );
}

export function isVegetarian(tags: { slug: string }[]): boolean {
    return tags.some(
        (tag) => tag.slug === 'vegetariana' || tag.slug === 'vegetarian',
    );
}

/**
 * The dozen promo: twelve units at the unit price are the regular price; the
 * percentage is floored so the promo never overstates the saving.
 */
export function dozenSaving(
    unitPrice: number,
    dozenPrice: number,
): { regular: number; percent: number } | null {
    const regular = unitPrice * 12;
    const percent = Math.floor(((regular - dozenPrice) * 100) / regular);

    return percent > 0 ? { regular, percent } : null;
}
