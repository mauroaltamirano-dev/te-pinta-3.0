import type {
    DeliveryType,
    OrderStatus,
    PaymentMethod,
    PaymentStatus,
} from '@/features/orders/types';

export interface CustomerOrderSummary {
    id: string;
    date: string;
    status: OrderStatus;
    delivery_type: DeliveryType;
    payment_status: PaymentStatus;
    payment_method: PaymentMethod | null;
    total: number;
}

export type CustomerSort = 'name' | 'orders_count' | 'historical_spend';
export type CustomerSortDirection = 'asc' | 'desc';

export interface Customer {
    id: number;
    name: string;
    phone: string | null;
    address: string | null;
    notes: string | null;
    historical_spend: number;
    orders_count: number;
    latest_order_date: string | null;
    orders?: CustomerOrderSummary[];
}

export interface PaginatedCustomers {
    data: Customer[];
    current_page: number;
    last_page: number;
    prev_page_url: string | null;
    next_page_url: string | null;
    total: number;
}
