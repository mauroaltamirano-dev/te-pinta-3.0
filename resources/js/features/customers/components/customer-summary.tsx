import {
    CalendarClock,
    MapPin,
    Phone,
    ReceiptText,
    WalletCards,
} from 'lucide-react';
import {
    formatCustomerCount,
    formatCustomerDate,
    formatCustomerMoney,
} from '@/features/customers/presentation';
import type { Customer } from '@/features/customers/types';

export function CustomerSummary({ customer }: { customer: Customer }) {
    const items = [
        {
            label: 'Teléfono',
            value: customer.phone ?? 'Sin teléfono',
            icon: Phone,
        },
        {
            label: 'Dirección',
            value: customer.address ?? 'Sin dirección',
            icon: MapPin,
        },
        {
            label: 'Pedidos',
            value: formatCustomerCount(customer.orders_count),
            icon: ReceiptText,
        },
        {
            label: 'Gasto histórico',
            value:
                customer.orders_count === 0
                    ? 'Sin compras registradas'
                    : formatCustomerMoney.format(customer.historical_spend),
            icon: WalletCards,
        },
        {
            label: 'Último pedido',
            value: formatCustomerDate(customer.latest_order_date),
            icon: CalendarClock,
        },
    ];

    return (
        <dl className="grid grid-cols-2 gap-px overflow-hidden rounded-lg bg-admin-border xl:grid-cols-5">
            {items.map(({ label, value, icon: Icon }) => (
                <div
                    key={label}
                    className="min-w-0 bg-admin-surface px-4 py-4 last:col-span-2 xl:last:col-span-1"
                >
                    <dt className="flex items-center gap-2 text-xs font-medium text-admin-text-tertiary">
                        <Icon
                            className="size-4 text-admin-accent-text"
                            strokeWidth={1.8}
                        />
                        {label}
                    </dt>
                    <dd className="mt-2 line-clamp-2 text-sm font-semibold whitespace-pre-wrap text-admin-text-primary">
                        {value}
                    </dd>
                </div>
            ))}
        </dl>
    );
}
