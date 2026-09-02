import type { InstagramPublicationStatus } from './types';

export const dayOptions = [
    { value: 1, shortLabel: 'Lun', label: 'Lunes' },
    { value: 2, shortLabel: 'Mar', label: 'Martes' },
    { value: 3, shortLabel: 'Mié', label: 'Miércoles' },
    { value: 4, shortLabel: 'Jue', label: 'Jueves' },
    { value: 5, shortLabel: 'Vie', label: 'Viernes' },
    { value: 6, shortLabel: 'Sáb', label: 'Sábado' },
    { value: 7, shortLabel: 'Dom', label: 'Domingo' },
] as const;

export const assetCategories = [
    { value: 'orders_open', label: 'Pedidos abiertos' },
    { value: 'menu', label: 'Menú' },
    { value: 'new_product', label: 'Nueva variedad' },
    { value: 'product', label: 'Producto' },
    { value: 'promotion', label: 'Promoción' },
    { value: 'other', label: 'Otro' },
] as const;

export const statusPresentation: Record<
    InstagramPublicationStatus,
    { label: string; className: string }
> = {
    pending: {
        label: 'Pendiente',
        className: 'border-amber-400/20 bg-amber-400/10 text-amber-200',
    },
    processing: {
        label: 'Procesando',
        className: 'border-sky-400/20 bg-sky-400/10 text-sky-200',
    },
    published: {
        label: 'Publicada',
        className: 'border-emerald-400/20 bg-emerald-400/10 text-emerald-200',
    },
    failed: {
        label: 'Error',
        className: 'border-red-400/20 bg-red-400/10 text-red-200',
    },
    cancelled: {
        label: 'Cancelada',
        className: 'border-white/10 bg-white/5 text-admin-text-secondary',
    },
};

export function categoryLabel(value: string): string {
    return (
        assetCategories.find((category) => category.value === value)?.label ??
        value
    );
}

export function describeDays(days: number[]): string {
    if (days.length === 7) {
        return 'Todos los días';
    }

    if (days.length === 0) {
        return 'Sin días activos';
    }

    const weekdays = [1, 2, 3, 4, 5];
    const mondayThroughSaturday = [...weekdays, 6];
    const sorted = [...days].sort((a, b) => a - b);

    if (JSON.stringify(sorted) === JSON.stringify(weekdays)) {
        return 'Lun–Vie';
    }

    if (JSON.stringify(sorted) === JSON.stringify(mondayThroughSaturday)) {
        return 'Lun–Sáb';
    }

    return dayOptions
        .filter((day) => days.includes(day.value))
        .map((day) => day.shortLabel)
        .join(', ');
}

export function formatRelativeRun(
    value: string | null | undefined,
): string | null {
    if (!value) {
        return null;
    }

    const date = new Date(value);

    if (Number.isNaN(date.getTime())) {
        return value;
    }

    const now = new Date();
    const today = new Date(now.getFullYear(), now.getMonth(), now.getDate());
    const target = new Date(
        date.getFullYear(),
        date.getMonth(),
        date.getDate(),
    );
    const days = Math.round((target.getTime() - today.getTime()) / 86_400_000);
    const day =
        days === 0
            ? 'hoy'
            : days === 1
              ? 'mañana'
              : new Intl.DateTimeFormat('es-AR', { weekday: 'short' }).format(
                    date,
                );
    const time = new Intl.DateTimeFormat('es-AR', {
        hour: '2-digit',
        minute: '2-digit',
    }).format(date);

    return `${day} ${time}`;
}

export function isStoryRatio(width: number, height: number): boolean {
    if (width <= 0 || height <= 0) {
        return false;
    }

    return Math.abs(width / height - 9 / 16) <= 0.035;
}

export function formatPublicationDate(value: string): string {
    const date = new Date(value);

    if (Number.isNaN(date.getTime())) {
        return value;
    }

    return new Intl.DateTimeFormat('es-AR', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    }).format(date);
}
