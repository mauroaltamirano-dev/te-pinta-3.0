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

export function formatInventoryQuantity(
    value: string,
    unit: DisplayUnit,
    locale = 'es-AR',
): string {
    const quantity = formatDecimalQuantity(value, locale);

    return `${quantity} ${inventoryUnitLabel(value, unit)}`;
}

export function inventoryUnitLabel(value: string, unit: DisplayUnit): string {
    const code = typeof unit === 'string' ? unit : unit.code;
    const symbol = typeof unit === 'string' ? unit : unit.symbol;
    const magnitude = value.trim().replace(/^[+-]/, '').replace(/\.0+$/, '');

    return code === 'unit' || symbol === 'unit'
        ? magnitude === '1'
            ? 'unidad'
            : 'unidades'
        : symbol;
}

export function inventoryUnitName(unit: InventoryUnit): string {
    return (
        {
            g: 'Gramo',
            kg: 'Kilogramo',
            ml: 'Mililitro',
            L: 'Litro',
            unit: 'Unidad',
            half_dozen: 'Media docena',
            dozen: 'Docena',
        }[unit.code] ?? unit.name
    );
}
