const money = new Intl.NumberFormat('es-AR', {
    style: 'currency',
    currency: 'ARS',
    maximumFractionDigits: 0,
});

/** Integer pesos in es-AR, e.g. "$ 16.650". The only admin money formatter. */
export function formatMoney(amount: number): string {
    return money.format(amount);
}

/** Inventory costs: null is an unknown cost, never a free one. */
export function formatCost(amount: number | null): string {
    return amount === null ? 'Sin costo conocido' : formatMoney(amount);
}
