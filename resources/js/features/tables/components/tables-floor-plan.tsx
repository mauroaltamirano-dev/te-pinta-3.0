import { Link } from '@inertiajs/react';
import { ReceiptText } from 'lucide-react';
import { useState } from 'react';
import { tableState, tableStates } from '@/features/tables/table-status';
import type {
    FloorTableSpot,
    TableBoardRow,
    TableFloorArea,
} from '@/features/tables/types';
import { formatMoney } from '@/lib/format';
import { cn } from '@/lib/utils';

const legend = (['free', 'occupied', 'bill'] as const).map(
    (state) => tableStates[state],
);

/** Position of an element stored as fractions of its area, the same way the floor editor draws it. */
function place(
    spot: Pick<FloorTableSpot, 'x' | 'y' | 'width' | 'height'>,
    rotation = 0,
) {
    return {
        left: `${Number(spot.x) * 100}%`,
        top: `${Number(spot.y) * 100}%`,
        width: `${Number(spot.width) * 100}%`,
        height: `${Number(spot.height) * 100}%`,
        transform: rotation ? `rotate(${rotation}deg)` : undefined,
    };
}

function describe(table: TableBoardRow): string {
    const parts = [tableStates[tableState(table)].label];

    if (table.session) {
        parts.push(
            table.session.mine ? 'Tu mesa' : table.session.waiter,
            formatMoney(table.session.total),
        );
    }

    return `Mesa ${table.code}: ${parts.join(', ')}`;
}

function TableSpot({
    spot,
    table,
}: {
    spot: FloorTableSpot;
    table: TableBoardRow;
}) {
    const state = tableState(table);
    const rotation = spot.shape === 'circle' ? 0 : spot.rotation;

    return (
        <div
            data-floor-table={table.code}
            className={cn(
                'absolute border-2',
                spot.shape === 'circle' ? 'rounded-full' : 'rounded-lg',
                tableStates[state].surface,
                state === 'bill' && 'ring-2 ring-warning/40',
            )}
            style={place(spot, rotation)}
        >
            {/* Upright and at least 44 px, however small or rotated the table is drawn. */}
            <Link
                href={`/admin/tables/${table.id}`}
                aria-label={describe(table)}
                className="@container absolute top-1/2 left-1/2 flex h-full min-h-11 w-full min-w-11 flex-col items-center justify-center overflow-hidden rounded-lg px-0.5 text-center leading-tight text-admin-text-primary outline-offset-2 focus-visible:outline-2 focus-visible:outline-admin-accent"
                style={{
                    transform: `translate(-50%, -50%) rotate(${-rotation}deg)`,
                }}
            >
                <span className="flex max-w-full items-center gap-0.5 text-xs font-semibold @[5rem]:text-sm">
                    <span className="truncate">{table.code}</span>
                    {state === 'bill' && (
                        <ReceiptText
                            aria-hidden
                            className="size-3.5 shrink-0 text-warning"
                        />
                    )}
                </span>
                <span className="hidden max-w-full truncate text-[11px] @[6rem]:block">
                    {tableStates[state].label}
                </span>
                {table.session && (
                    <>
                        <span className="hidden max-w-full truncate text-[11px] font-semibold tabular-nums @[3.75rem]:block">
                            {formatMoney(table.session.total)}
                        </span>
                        <span className="hidden max-w-full truncate text-[11px] text-muted-foreground @[5rem]:block">
                            {table.session.mine
                                ? 'Tu mesa'
                                : table.session.waiter}
                        </span>
                    </>
                )}
            </Link>
        </div>
    );
}

/** Waiter and total of the area's open tables, readable even when the plan draws them small. */
function OccupiedTables({
    area,
    rows,
}: {
    area: TableFloorArea;
    rows: Map<number, TableBoardRow>;
}) {
    const open = area.tables
        .map((spot) => rows.get(spot.id))
        .filter((table): table is TableBoardRow => !!table?.session)
        .sort((a, b) => a.code.localeCompare(b.code, 'es', { numeric: true }));

    if (open.length === 0) {
        return null;
    }

    return (
        <ul
            aria-label={`Mesas ocupadas en ${area.name}`}
            className="flex flex-wrap gap-2"
        >
            {open.map((table) => {
                const state = tableState(table);

                return (
                    <li key={table.id} className="max-w-full">
                        <Link
                            href={`/admin/tables/${table.id}`}
                            className={cn(
                                'flex min-h-11 max-w-full items-center gap-2 rounded-lg border-2 px-3 text-sm whitespace-nowrap',
                                tableStates[state].surface,
                            )}
                        >
                            <span className="font-semibold">{table.code}</span>
                            {state === 'bill' && (
                                <span className="flex items-center gap-1 text-warning">
                                    <ReceiptText
                                        aria-hidden
                                        className="size-3.5"
                                    />
                                    Pide la cuenta
                                </span>
                            )}
                            <span className="min-w-0 truncate text-muted-foreground">
                                {table.session!.mine
                                    ? 'Tu mesa'
                                    : table.session!.waiter}
                            </span>
                            <span className="font-semibold tabular-nums">
                                {formatMoney(table.session!.total)}
                            </span>
                        </Link>
                    </li>
                );
            })}
        </ul>
    );
}

export function TablesFloorPlan({
    areas,
    tables,
}: {
    areas: TableFloorArea[];
    tables: TableBoardRow[];
}) {
    const rows = new Map(tables.map((table) => [table.id, table]));
    const [selected, setSelected] = useState(areas[0]?.id);
    // A poll can drop the selected area (its last listed table was closed and deactivated).
    const current = areas.some((area) => area.id === selected)
        ? selected
        : areas[0]?.id;
    const tabs = areas.length > 1;

    return (
        <div className="grid gap-4">
            <ul
                aria-label="Referencias"
                className="flex flex-wrap gap-x-4 gap-y-1 text-xs text-muted-foreground"
            >
                {legend.map((item) => (
                    <li key={item.label} className="flex items-center gap-1.5">
                        <span
                            aria-hidden
                            className={cn(
                                'size-3 rounded-sm border-2',
                                item.surface,
                            )}
                        />
                        {item.label}
                    </li>
                ))}
            </ul>
            {tabs && (
                // On the phone one area at a time; wider screens show every area.
                <div
                    role="tablist"
                    aria-label="Áreas"
                    className="flex gap-2 overflow-x-auto md:hidden"
                >
                    {areas.map((area) => (
                        <button
                            key={area.id}
                            type="button"
                            role="tab"
                            id={`floor-tab-${area.id}`}
                            aria-selected={area.id === current}
                            aria-controls={`floor-area-${area.id}`}
                            onClick={() => setSelected(area.id)}
                            className={cn(
                                'min-h-11 shrink-0 rounded-lg border border-admin-border px-4 text-sm font-medium',
                                area.id === current
                                    ? 'border-admin-accent bg-admin-surface text-admin-text-primary'
                                    : 'text-muted-foreground',
                            )}
                        >
                            {area.name}
                        </button>
                    ))}
                </div>
            )}
            <div className={cn('grid gap-6', tabs && 'xl:grid-cols-2')}>
                {areas.map((area) => (
                    <section
                        key={area.id}
                        id={`floor-area-${area.id}`}
                        aria-labelledby={`floor-area-title-${area.id}`}
                        className={cn(
                            'grid content-start gap-2',
                            area.id !== current && 'hidden md:grid',
                        )}
                    >
                        <h2
                            id={`floor-area-title-${area.id}`}
                            className={cn(
                                'text-sm font-semibold',
                                tabs && 'sr-only md:not-sr-only',
                            )}
                        >
                            {area.name}
                        </h2>
                        <div
                            role="group"
                            aria-label={`Plano de ${area.name}`}
                            data-testid="tables-floor-plan"
                            className="relative w-full overflow-hidden rounded-xl border border-admin-border bg-admin-canvas"
                            style={{
                                aspectRatio: `${area.width} / ${area.height}`,
                            }}
                        >
                            {area.obstacles.map((obstacle, index) => (
                                <div
                                    key={index}
                                    aria-hidden
                                    className={cn(
                                        'absolute flex items-center justify-center overflow-hidden border border-admin-border bg-admin-surface-raised text-[10px] text-muted-foreground',
                                        obstacle.type === 'circle'
                                            ? 'rounded-full'
                                            : 'rounded-md',
                                    )}
                                    style={place(obstacle)}
                                >
                                    <span className="truncate px-1">
                                        {obstacle.label}
                                    </span>
                                </div>
                            ))}
                            {area.tables.map((spot) => {
                                const table = rows.get(spot.id);

                                return (
                                    table && (
                                        <TableSpot
                                            key={spot.id}
                                            spot={spot}
                                            table={table}
                                        />
                                    )
                                );
                            })}
                        </div>
                        <OccupiedTables area={area} rows={rows} />
                    </section>
                ))}
            </div>
        </div>
    );
}
