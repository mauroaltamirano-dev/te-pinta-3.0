import type { InventoryUnit } from './types';

type DisplayUnit = Pick<InventoryUnit, 'code' | 'symbol'> | string;

/** Display only: never round or coerce the NUMERIC quantity into a JS Number. */
export function formatDecimalQuantity(value: string, locale = 'es-AR'): string {
    const match = /^([+-]?)(\d+)(?:\.(\d+))?$/.exec(value.trim());

    if (!match) {
        return value;
    }

    const [, sign, integer, rawFraction = ''] = match;
    const fraction = rawFraction.replace(/0+$/, '');
    const isZero = /^0+$/.test(integer) && fraction === '';
    const groupedInteger = new Intl.NumberFormat(locale).format(
        BigInt(integer),
    );
    const decimalSeparator =
        new Intl.NumberFormat(locale)
            .formatToParts(1.1)
            .find((part) => part.type === 'decimal')?.value ?? ',';

    return `${isZero ? '' : sign}${groupedInteger}${fraction ? `${decimalSeparator}${fraction}` : ''}`;
}

/** Prefills an editable field: drops trailing zeros but keeps the dot the backend expects. */
export function editableDecimal(value: string): string {
    return value
        .trim()
        .replace(/(\.\d*?)0+$/, '$1')
        .replace(/\.$/, '');
}

export function formatInventoryQuantity(
    value: string,
    unit: DisplayUnit,
    locale = 'es-AR',
): string {
    const quantity = formatDecimalQuantity(value, locale);

    return `${quantity} ${inventoryUnitLabel(value, unit)}`;
}

// Count units are labelled by code; their stored symbols are English ("half dozen").
const countUnitLabels: Record<string, [string, string]> = {
    unit: ['unidad', 'unidades'],
    half_dozen: ['media docena', 'medias docenas'],
    dozen: ['docena', 'docenas'],
};

export function inventoryUnitLabel(value: string, unit: DisplayUnit): string {
    const code = typeof unit === 'string' ? unit : unit.code;
    const symbol = typeof unit === 'string' ? unit : unit.symbol;
    const labels =
        countUnitLabels[code] ?? countUnitLabels[symbol.replace(' ', '_')];
    const magnitude = value.trim().replace(/^[+-]/, '').replace(/\.0+$/, '');

    return labels ? labels[magnitude === '1' ? 0 : 1] : symbol;
}

export function inventoryUnitName(
    unit: Pick<InventoryUnit, 'code'> & { name?: string },
): string {
    return (
        {
            g: 'Gramo',
            kg: 'Kilogramo',
            ml: 'Mililitro',
            L: 'Litro',
            unit: 'Unidad',
            half_dozen: 'Media docena',
            dozen: 'Docena',
        }[unit.code] ??
        unit.name ??
        unit.code
    );
}

export const itemKinds: Record<string, string> = {
    raw_material: 'Materia prima',
    semi_finished: 'Semielaborado',
    finished: 'Producto terminado',
    packaging: 'Packaging',
    consumable: 'Consumible',
    other: 'Otro',
};

export function inventoryItemKindLabel(kind: string | null): string {
    return kind ? (itemKinds[kind] ?? kind) : 'Clasificación pendiente';
}

/** Exact decimal × integer on the string, so display never drifts from the backend. */
export function multiplyDecimal(value: string, factor: bigint): string | null {
    const match = /^(\d+)(?:\.(\d+))?$/.exec(value.trim());

    if (!match) {
        return null;
    }

    const [, integer, fraction = ''] = match;
    const product = (BigInt(integer + fraction) * factor)
        .toString()
        .padStart(fraction.length + 1, '0');
    const cut = product.length - fraction.length;

    return editableDecimal(
        fraction ? `${product.slice(0, cut)}.${product.slice(cut)}` : product,
    );
}

/** Live example for a consumption quantity, which is always per 1 sold unit. */
export function consumptionExample(
    quantity: string,
    unit: DisplayUnit,
): string | null {
    const dozen = multiplyDecimal(quantity, 12n);

    if (dozen === null || !quantity.trim()) {
        return null;
    }

    return `Una docena descuenta 12 × ${formatInventoryQuantity(quantity, unit)} = ${formatInventoryQuantity(dozen, unit)}`;
}
