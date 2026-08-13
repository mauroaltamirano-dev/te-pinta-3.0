import type {
    DeliveryPeriod,
    DeliveryType,
    OrderCondition,
    OrderStatus,
    PaymentStatus,
} from './types';

export interface AdminProductOption {
    id: number;
    name: string;
    description: string | null;
    image_url: string | null;
    category: { id: number; name: string };
    unit_price: number;
    half_dozen_price: number;
    dozen_price: number;
}

export interface AdminCustomerOption {
    id: number;
    name: string;
    phone: string | null;
    address: string | null;
}

export interface AdminAddonOption {
    id: number;
    name: string;
    price: number;
}

export interface AdminOrderForm {
    items: Array<{ product_id: number; quantity: number }>;
    addons: Array<{ addon_id: number; quantity: number }>;
    condition: OrderCondition;
    customer_id: number | null;
    customer: { name: string; phone: string; address: string };
    delivery_type: DeliveryType;
    delivery_date: string;
    delivery_period: DeliveryPeriod;
    delivery_time: string;
    delivery_address: string;
    notes: string;
    payment_status: PaymentStatus;
}

export interface AdminOrderOptions {
    products: AdminProductOption[];
    customers: AdminCustomerOption[];
    addons: AdminAddonOption[];
    today: string;
}

export interface AdminCreatedOrder {
    order_id: string;
    status: OrderStatus;
    payment_status: PaymentStatus;
    total: number;
}
