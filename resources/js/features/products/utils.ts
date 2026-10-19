export function formatPesos(amount: number): string {
    return new Intl.NumberFormat('es-AR', {
        style: 'currency',
        currency: 'ARS',
        maximumFractionDigits: 0,
    }).format(amount);
}

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
