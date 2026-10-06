import type { FloorArea, FloorShape, GeometryPatch } from './types';

export function decimal(value: number): string {
    return (Math.round(value * 10000) / 10000).toFixed(4);
}

// Mirrors the conservative backend envelope; persistence remains backend-authoritative.
export function constrainGeometry(
    patch: GeometryPatch,
    area: Pick<FloorArea, 'width' | 'height'>,
    shape: FloorShape,
    rotation: number,
): GeometryPatch {
    let width = Math.max(0.01, Math.min(0.8, Number(patch.width)));
    let height = Math.max(0.01, Math.min(0.8, Number(patch.height)));

    if (shape === 'circle') {
        width = Math.min(width, (0.8 * area.height) / area.width);
        height = (width * area.width) / area.height;
    }

    const angle = shape === 'circle' ? 0 : rotation % 180;
    const c = angle === 0 ? 1 : angle === 90 ? 0 : 0.7072;
    const s = angle === 0 ? 0 : angle === 90 ? 1 : 0.7072;
    const extentX = c * width + (s * height * area.height) / area.width;
    const extentY = (s * width * area.width) / area.height + c * height;
    const scale = Math.min(1, 0.99 / extentX, 0.99 / extentY);
    width *= scale;
    height *= scale;
    const extraX = (extentX * scale - width) / 2;
    const extraY = (extentY * scale - height) / 2;
    // Round inward by one coordinate quantum before backend validation.
    const x = Math.max(
        Math.max(0, extraX) + 0.0001,
        Math.min(1 - width - Math.max(0, extraX) - 0.0001, Number(patch.x)),
    );
    const y = Math.max(
        Math.max(0, extraY) + 0.0001,
        Math.min(1 - height - Math.max(0, extraY) - 0.0001, Number(patch.y)),
    );

    return {
        x: decimal(x),
        y: decimal(y),
        width: decimal(width),
        height: decimal(height),
    };
}
