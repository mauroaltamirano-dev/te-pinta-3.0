import type { DashboardPeriod } from '@/features/dashboard/types';
import {
    deliveryPeriodLabels,
    orderStatusLabels,
    formatOrderDate,
} from '@/features/orders/presentation';
import type { OrderStatus } from '@/features/orders/types';

export const dashboardPeriodLabels: Record<DashboardPeriod, string> = {
    today: 'Hoy',
    week: 'Semana',
    month: 'Mes',
    all: 'Desde siempre',
};

export const dashboardStatusLabels: Record<OrderStatus, string> =
    orderStatusLabels;

export function dashboardSalesLabel(period: DashboardPeriod): string {
    if (period === 'today') {
        return 'Ventas del día';
    }

    if (period === 'week') {
        return 'Ventas de la semana';
    }

    return period === 'month' ? 'Ventas del mes' : 'Ventas desde siempre';
}

export function dashboardCollectedLabel(period: DashboardPeriod): string {
    if (period === 'today') {
        return 'Ingresos cobrados hoy';
    }

    if (period === 'week') {
        return 'Ingresos cobrados esta semana';
    }

    return period === 'month'
        ? 'Ingresos cobrados este mes'
        : 'Ingresos cobrados desde siempre';
}

export function formatDashboardBucket(
    bucket: string,
    period: DashboardPeriod,
): string {
    if (period === 'today') {
        return (
            deliveryPeriodLabels[bucket as keyof typeof deliveryPeriodLabels] ??
            bucket
        );
    }

    return period === 'all'
        ? formatOrderDate(`${bucket}-01`).slice(3)
        : formatOrderDate(bucket).slice(0, 5);
}

export function formatDashboardBucketAccessible(
    bucket: string,
    period: DashboardPeriod,
): string {
    if (period === 'today') {
        return formatDashboardBucket(bucket, period);
    }

    return period === 'all'
        ? formatOrderDate(`${bucket}-01`).slice(3)
        : formatOrderDate(bucket);
}

export function formatDeliveryMoment(
    time: string | null,
    period: keyof typeof deliveryPeriodLabels,
): string {
    return time ? time.slice(0, 5) : deliveryPeriodLabels[period];
}

export const formatDashboardNumber = new Intl.NumberFormat('es-AR');

export const formatDashboardDozens = new Intl.NumberFormat('es-AR', {
    maximumFractionDigits: 2,
});

export const formatDashboardPercentage = new Intl.NumberFormat('es-AR', {
    style: 'percent',
    maximumFractionDigits: 0,
});
