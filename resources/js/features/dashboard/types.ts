import type { MarketingDashboardSummary } from '@/features/marketing/types';
import type {
    AdminOrder,
    DeliveryPeriod,
    DeliveryType,
    OrderStatus,
    PaymentStatus,
} from '@/features/orders/types';

export type DashboardPeriod = 'today' | 'week' | 'month' | 'all';

export interface DashboardOrder {
    id: string;
    customer_name: string;
    delivery_period: DeliveryPeriod;
    delivery_time: string | null;
    delivery_type: DeliveryType;
    status: Exclude<OrderStatus, 'cancelled'>;
    payment_status: PaymentStatus;
    total: number;
}

export interface DashboardData {
    period: DashboardPeriod;
    range: {
        start_date: string;
        end_date: string;
    };
    kpis: {
        sales_total: number;
        collected_total: number;
        orders_count: number;
        pending_confirmation_count: number;
        unpaid_count: number;
        empanadas_count: number;
    };
    today_status_distribution: Array<{
        status: Exclude<OrderStatus, 'cancelled'>;
        orders_count: number;
    }>;
    urgent_orders: DashboardOrder[];
    today_orders: DashboardOrder[];
    top_products: Array<{
        product_id: number;
        name: string;
        units_count: number;
    }>;
    delivery_distribution: Array<{
        type: DeliveryType;
        orders_count: number;
    }>;
    condition_distribution: Array<{
        condition: 'raw' | 'cooked';
        units_count: number;
    }>;
    evolution: Array<{
        bucket: string;
        total: number;
    }>;
}

export interface DashboardPageProps {
    dashboard: DashboardData;
    marketingSummary: MarketingDashboardSummary;
    selectedOrder?: AdminOrder | null;
    selectedOrderSummary?: string | null;
}
