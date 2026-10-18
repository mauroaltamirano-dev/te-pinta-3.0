import { useRef } from 'react';
import type { PointerEvent } from 'react';
import { cn } from '@/lib/utils';
import { constrainGeometry } from './geometry';
import { tableLabel } from './types';
import type {
    FloorArea,
    FloorSelection,
    GeometryPatch,
    FloorShape,
} from './types';

type Props = {
    area: FloorArea;
    editable?: boolean;
    selected?: FloorSelection | null;
    onSelect?: (selection: FloorSelection) => void;
    onGeometryChange?: (
        selection: FloorSelection,
        patch: GeometryPatch,
    ) => void;
};

export function FloorPlan({
    area,
    editable = false,
    selected,
    onSelect,
    onGeometryChange,
}: Props) {
    const floor = useRef<HTMLDivElement>(null);
    const gesture = useRef<{
        pointer: number;
        selection: FloorSelection;
        startX: number;
        startY: number;
        geometry: GeometryPatch;
        shape: FloorShape;
        rotation: number;
        resize: boolean;
    } | null>(null);
    const begin = (
        event: PointerEvent<HTMLButtonElement>,
        selection: FloorSelection,
        geometry: GeometryPatch,
        shape: FloorShape,
        rotation: number,
        resize = false,
    ) => {
        onSelect?.(selection);

        if (!editable || event.button !== 0) {
            return;
        }

        event.currentTarget.setPointerCapture(event.pointerId);
        gesture.current = {
            pointer: event.pointerId,
            selection,
            startX: event.clientX,
            startY: event.clientY,
            geometry,
            shape,
            rotation,
            resize,
        };
    };
    const move = (event: PointerEvent<HTMLButtonElement>) => {
        const current = gesture.current;
        const bounds = floor.current?.getBoundingClientRect();

        if (
            !current ||
            !bounds ||
            current.pointer !== event.pointerId ||
            !editable
        ) {
            return;
        }

        const dx = (event.clientX - current.startX) / bounds.width;
        const dy = (event.clientY - current.startY) / bounds.height;
        const patch = current.resize
            ? {
                  ...current.geometry,
                  width: String(Number(current.geometry.width) + dx),
                  height: String(Number(current.geometry.height) + dy),
              }
            : {
                  ...current.geometry,
                  x: String(Number(current.geometry.x) + dx),
                  y: String(Number(current.geometry.y) + dy),
              };
        onGeometryChange?.(
            current.selection,
            constrainGeometry(patch, area, current.shape, current.rotation),
        );
    };
    const end = () => {
        gesture.current = null;
    };
    const renderItem = (
        selection: FloorSelection,
        geometry: GeometryPatch,
        shape: FloorShape,
        rotation: number,
        label: string,
        inactive = false,
    ) => {
        const isSelected =
            selected?.kind === selection.kind && selected.key === selection.key;

        return (
            <div
                key={`${selection.kind}-${selection.key}`}
                data-floor-item={selection.kind}
                data-item-key={selection.key}
                className={cn(
                    'absolute border-2',
                    shape === 'circle' ? 'rounded-full' : 'rounded-lg',
                    selection.kind === 'obstacle'
                        ? 'border-admin-border bg-admin-surface-raised'
                        : 'border-admin-border bg-admin-surface',
                    isSelected &&
                        'border-admin-accent ring-2 ring-admin-accent/30',
                    inactive && 'border-dashed opacity-70',
                )}
                style={{
                    left: `${Number(geometry.x) * 100}%`,
                    top: `${Number(geometry.y) * 100}%`,
                    width: `${Number(geometry.width) * 100}%`,
                    height: `${Number(geometry.height) * 100}%`,
                    transform: `rotate(${rotation}deg)`,
                }}
            >
                <button
                    type="button"
                    aria-label={`${label}${inactive ? ', inactiva' : ''}`}
                    aria-pressed={isSelected}
                    disabled={!onSelect}
                    onClick={() => onSelect?.(selection)}
                    onPointerDown={(event) =>
                        begin(event, selection, geometry, shape, rotation)
                    }
                    onPointerMove={move}
                    onPointerUp={end}
                    onPointerCancel={end}
                    onLostPointerCapture={end}
                    className={cn(
                        'absolute top-1/2 left-1/2 flex h-full min-h-11 w-full min-w-11 -translate-x-1/2 -translate-y-1/2 flex-col items-center justify-center rounded-lg px-0.5 text-xs font-semibold text-admin-text-primary outline-offset-2 focus-visible:outline-2 focus-visible:outline-admin-accent',
                        editable &&
                            'cursor-grab touch-none active:cursor-grabbing',
                    )}
                    style={{
                        transform: `translate(0, 0) rotate(${-rotation}deg)`,
                    }}
                >
                    <span className="max-w-full truncate">{label}</span>
                    {inactive && (
                        <span className="text-[10px] font-normal">
                            Inactiva
                        </span>
                    )}
                </button>
                {editable && isSelected && (
                    <button
                        type="button"
                        aria-label={`Redimensionar ${label}`}
                        onPointerDown={(event) =>
                            begin(
                                event,
                                selection,
                                geometry,
                                shape,
                                rotation,
                                true,
                            )
                        }
                        onPointerMove={move}
                        onPointerUp={end}
                        onPointerCancel={end}
                        onLostPointerCapture={end}
                        className="absolute -right-2 -bottom-2 z-10 hidden size-11 touch-none items-center justify-center rounded-lg border border-admin-accent bg-admin-surface text-admin-accent-text focus-visible:outline-2 focus-visible:outline-admin-accent md:flex"
                    >
                        ↘
                    </button>
                )}
            </div>
        );
    };

    return (
        <div
            ref={floor}
            role="group"
            aria-label={`Plano de ${area.name}`}
            data-testid="floor-plan"
            className="relative w-full overflow-hidden rounded-xl border border-admin-border bg-admin-canvas"
            style={{ aspectRatio: `${area.width} / ${area.height}` }}
        >
            {area.obstacles.map((obstacle) =>
                renderItem(
                    { kind: 'obstacle', key: obstacle.key },
                    {
                        x: obstacle.x,
                        y: obstacle.y,
                        width: obstacle.width,
                        height: obstacle.height,
                    },
                    obstacle.type,
                    0,
                    obstacle.label || 'Obstáculo',
                ),
            )}
            {area.tables.map((table) =>
                renderItem(
                    { kind: 'table', key: table.key },
                    {
                        x: table.position_x,
                        y: table.position_y,
                        width: table.width,
                        height: table.height,
                    },
                    table.shape,
                    table.rotation,
                    tableLabel(table),
                    !table.is_active,
                ),
            )}
        </div>
    );
}
