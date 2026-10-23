import type {
    AdminAddonOption,
    AdminProductOption,
} from '@/features/orders/admin-order-types';
import type { PaymentMethod } from '@/features/orders/types';
import type { FloorObstacle, FloorShape } from '@/features/restaurant/types';

export interface ServiceTable {
    id: number;
    code: string;
    area: string;
    /** The table and its area are active, so a free table can be opened. */
    available: boolean;
}

export interface TableSessionSummary {
    id: number;
    waiter: string;
    mine: boolean;
    /** HH:MM in the restaurant's time zone. */
    opened_at: string;
    bill_requested: boolean;
    total: number;
}

export interface TableBoardRow extends ServiceTable {
    session: TableSessionSummary | null;
}

export type TableRoundKind = 'order' | 'void';

export interface TableRoundView {
    id: number;
    sequence: number;
    kind: TableRoundKind;
    time: string;
    note: string | null;
    /** "Cajero Lu" when someone other than the table's waiter confirmed it. */
    added_by: string | null;
    items: Array<{ name: string; quantity: number }>;
}

export interface TableLine {
    product_id: number | null;
    addon_id: number | null;
    name: string;
    quantity: number;
}

export interface TableDetail {
    table: ServiceTable;
    session:
        | (TableSessionSummary & {
              can_act: boolean;
              rounds: TableRoundView[];
              lines: TableLine[];
          })
        | null;
    products: AdminProductOption[];
    addons: AdminAddonOption[];
    can_cashier: boolean;
    payment_methods: PaymentMethod[];
    missing_consumption: string[];
}

/** A table's place on the floor, as the floor editor stored it: fractions of its area. */
export interface FloorTableSpot {
    id: number;
    shape: FloorShape;
    x: string;
    y: string;
    width: string;
    height: string;
    rotation: number;
}

/** An area of the board's floor, with only the tables the board lists. */
export interface TableFloorArea {
    id: number;
    name: string;
    width: number;
    height: number;
    obstacles: Array<Omit<FloorObstacle, 'key'>>;
    tables: FloorTableSpot[];
}
