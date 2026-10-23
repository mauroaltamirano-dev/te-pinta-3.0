import type { TableBoardRow } from './types';

export type TableState = 'bill' | 'occupied' | 'free' | 'inactive';

/** How each state reads on the board: a badge on the list, a filled shape on the plan. */
export const tableStates: Record<
    TableState,
    { label: string; badge: string; surface: string }
> = {
    bill: {
        label: 'Pide la cuenta',
        badge: 'bg-warning-subtle text-warning',
        surface: 'border-warning bg-warning-subtle',
    },
    occupied: {
        label: 'Ocupada',
        badge: 'bg-info-subtle text-info',
        surface: 'border-info bg-info-subtle',
    },
    free: {
        label: 'Libre',
        badge: 'bg-success-subtle text-success',
        surface: 'border-success bg-success-subtle',
    },
    inactive: {
        label: 'Inactiva',
        badge: 'bg-muted text-muted-foreground',
        surface: 'border-dashed border-admin-border bg-muted opacity-60',
    },
};

export function tableState(table: TableBoardRow): TableState {
    if (table.session?.bill_requested) {
        return 'bill';
    }

    if (table.session) {
        return 'occupied';
    }

    return table.available ? 'free' : 'inactive';
}
