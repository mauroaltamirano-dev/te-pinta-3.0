import type {
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
    pending_confirmation: 'bg-[rgba(255,181,114,0.20)] text-[#ffb572]',
    waiting: 'bg-[rgba(255,218,7,0.20)] text-[#fff000]',
    prepared: 'bg-[rgba(146,144,254,0.20)] text-[#9290fe]',
    delivered: 'bg-[rgba(107,226,190,0.24)] text-[#50d1aa]',
    cancelled: 'bg-[rgba(255,124,163,0.20)] text-[#ff7ca3]',
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
    raw: 'Crudo',
    cooked: 'Cocinado',
};

export const deliveryTypeLabels: Record<DeliveryType, string> = {
    delivery: 'Envío',
    pickup: 'Retiro',
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

export function formatOrderDate(value: string): string {
    const match = /^(\d{4})-(\d{2})-(\d{2})$/.exec(value);

    return match ? `${match[3]}/${match[2]}/${match[1]}` : value;
}
