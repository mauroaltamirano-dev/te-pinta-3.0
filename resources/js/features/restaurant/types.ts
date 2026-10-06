export type FloorShape = 'rectangle' | 'circle';
export type FloorTable = {
    id?: number;
    key: string;
    code: string;
    name: string | null;
    shape: FloorShape;
    position_x: string;
    position_y: string;
    width: string;
    height: string;
    rotation: number;
    capacity: number | null;
    is_active: boolean;
};
export type FloorObstacle = {
    key: string;
    type: FloorShape;
    x: string;
    y: string;
    width: string;
    height: string;
    label?: string | null;
};
export type FloorArea = {
    id: number;
    name: string;
    width: number;
    height: number;
    is_active: boolean;
    layout_version: number;
    layout_schema_version: number;
    tables: FloorTable[];
    obstacles: FloorObstacle[];
};
export type FloorSelection = { kind: 'table' | 'obstacle'; key: string };
export type GeometryPatch = {
    x: string;
    y: string;
    width: string;
    height: string;
};

export function hydrateArea(area: FloorArea): FloorArea {
    return {
        ...area,
        tables: area.tables.map((table) => ({
            ...table,
            key: String(table.id),
        })),
        obstacles: area.obstacles.map((obstacle, index) => ({
            ...obstacle,
            key: String(index),
        })),
    };
}

export function tableLabel(table: FloorTable): string {
    return (
        table.name?.trim() ||
        (/^\d+$/.test(table.code) ? `Mesa ${table.code}` : table.code)
    );
}
