/**
 * Display-only line subtotal in integer pesos, mirroring CreatePurchase:
 * a total price is the line total; a unit price is multiplied by the quantity.
 * "fractional" means the backend will reject the line for centavos.
 */
export function purchaseLineSubtotal(
    quantity: string,
    mode: 'total' | 'unit',
    price: string,
): number | 'fractional' | null {
    if (!/^\d{1,12}$/.test(price.trim())) {
        return null;
    }

    if (mode === 'total') {
        return Number(price);
    }

    const match = /^(\d{1,14})(?:\.(\d{1,6}))?$/.exec(quantity.trim());

    if (!match) {
        return null;
    }

    const [, integer, fraction = ''] = match;
    const scale = 10n ** BigInt(fraction.length);
    const scaled = BigInt(price.trim()) * BigInt(integer + fraction);

    return scaled % scale === 0n ? Number(scaled / scale) : 'fractional';
}
