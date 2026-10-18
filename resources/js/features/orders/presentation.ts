import type {
    AdminOrder,
    DeliveryPeriod,
    DeliveryType,
    OrderCondition,
    OrderStatus,
    PaymentMethod,
    PaymentStatus,
} from '@/features/orders/types';

export const orderStatusLabels: Record<OrderStatus, string> = {
    pending_confirmation: 'En espera',
    waiting: 'Confirmado',
    prepared: 'Preparado',
    delivered: 'Entregado',
    cancelled: 'Cancelado',
};

export const orderStatusStyles: Record<OrderStatus, string> = {
    pending_confirmation: 'bg-warning-subtle text-warning',
    waiting: 'bg-info-subtle text-info',
    prepared: 'bg-prepared-subtle text-prepared',
    delivered: 'bg-success-subtle text-success',
    cancelled: 'bg-danger-subtle text-danger',
};

export const paymentStatusLabels: Record<PaymentStatus, string> = {
    paid: 'Pagado',
    unpaid: 'No pagado',
    not_required: 'No requiere pago',
};

export const paymentMethodLabels: Record<PaymentMethod, string> = {
    cash: 'Efectivo',
    bank_transfer: 'Transferencia',
    debit_card: 'Débito',
};

export const orderConditionLabels: Record<OrderCondition, string> = {
    raw: 'Crudas',
    cooked: 'Cocinadas',
};

export const deliveryTypeLabels: Record<DeliveryType, string> = {
    delivery: 'Envío',
    pickup: 'Retiro',
    dine_in: 'Salón',
};

export const deliveryPeriodLabels: Record<DeliveryPeriod, string> = {
    midday: 'Mediodía',
    afternoon: 'Tarde',
    night: 'Noche',
};

export const pricingModeLabels: Record<string, string> = {
    unit: 'Unidad',
    half_dozen: 'Media docena',
    dozen: 'Docena',
    combined_dozen: 'Docena combinada',
};

export const orderStatuses = Object.keys(orderStatusLabels) as OrderStatus[];

export const formatOrderMoney = new Intl.NumberFormat('es-AR', {
    style: 'currency',
    currency: 'ARS',
    maximumFractionDigits: 0,
});

export function formatOrderCustomer(order: AdminOrder): string {
    return (
        order.customer_name ??
        order.customer?.name ??
        (order.delivery_type === 'dine_in'
            ? 'Salón · sin cliente asociado'
            : 'Sin cliente asociado')
    );
}

export function formatDeliveryPeriod(period: DeliveryPeriod | null): string {
    return period === null ? 'Sin programación' : deliveryPeriodLabels[period];
}

export function formatOrderDate(value: string | null): string {
    if (value === null) {
        return 'Sin programación';
    }

    const match = /^(\d{4})-(\d{2})-(\d{2})$/.exec(value);

    return match ? `${match[3]}/${match[2]}/${match[1]}` : value;
}
