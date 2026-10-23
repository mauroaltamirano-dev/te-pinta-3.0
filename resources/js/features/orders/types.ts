export type OrderSource = 'admin' | 'public_checkout' | 'pos';
export type OrderCondition = 'raw' | 'cooked';
export type DeliveryType = 'delivery' | 'pickup' | 'dine_in';
export type DeliveryPeriod = 'midday' | 'afternoon' | 'night';
export type OrderStatus =
    'pending_confirmation' | 'waiting' | 'prepared' | 'delivered' | 'cancelled';
export type PaymentStatus = 'paid' | 'unpaid' | 'not_required';
export type PaymentMethod = 'cash' | 'bank_transfer' | 'debit_card';
export type DiscountType = 'percentage' | 'fixed_amount';
export type OrdersView = 'grid' | 'list';

export interface PublicProduct {
    slug: string;
    name: string;
    description: string | null;
    long_description: string | null;
    public_ingredients: string | null;
    image_url: string | null;
    category: { slug: string; name: string };
    /** A filter only; null when the product has none or it is inactive. */
    subcategory: { slug: string; name: string; sort_order: number } | null;
    tags: Array<{ slug: string; name: string }>;
    pricing_family: 'empanada' | 'unit';
    prices: { unit: number; half_dozen: number | null; dozen: number | null };
}
export interface OrderAddon {
    id?: number;
    slug: string;
    name: string;
    price: number;
    is_available?: boolean;
    sort_order?: number;
}
export interface CartLine {
    product_slug: string;
    quantity: number;
}
export interface CartAddonLine {
    addon_slug: string;
    quantity: number;
}
export interface PriceQuoteLine {
    format?: 'unit' | 'half_dozen' | 'dozen' | 'combined_dozen';
    pricingMode?: string;
    name?: string;
    productName?: string | null;
    quantity: number;
    unit_price?: number;
    unitPrice?: number;
    price?: number;
    subtotal?: number;
    lineTotal?: number;
    cookedUnitPrice?: number;
    cookedLineTotal?: number;
    rawDiscount?: number;
    composition?: Array<{
        name?: string;
        product_name?: string;
        quantity: number;
    }>;
}
export interface PriceQuote {
    lines?: PriceQuoteLine[];
    items?: PriceQuoteLine[];
    toppings?: Array<{
        name: string;
        quantity: number;
        unit_price?: number;
        unitPrice?: number;
        subtotal?: number;
        lineTotal?: number;
    }>;
    addons?: Array<{
        name: string;
        quantity: number;
        unit_price?: number;
        unitPrice?: number;
        subtotal?: number;
        lineTotal?: number;
    }>;
    products_subtotal?: number;
    empanadas_subtotal?: number;
    products_cooked_subtotal?: number;
    raw_discount?: number;
    products_base_subtotal?: number;
    cooking_surcharge?: number;
    toppings_subtotal?: number;
    addons_subtotal?: number;
    shipping_fee?: number;
    combined_dozen_savings?: number;
    savings?: number;
    discountable_subtotal?: number;
    discount_type?: DiscountType | null;
    discount_value?: number | null;
    discount_amount?: number;
    rounding_adjustment?: number;
    total: number;
}
export interface CreatedOrderResponse {
    order_id: string;
    status: OrderStatus;
    payment_status: PaymentStatus;
    total: number;
    summary: string;
    whatsapp_url: string;
}
export interface AdminOrder {
    id: string;
    customer: { id: number; name: string | null } | null;
    customer_name: string | null;
    source: OrderSource;
    status: OrderStatus;
    payment_status: PaymentStatus;
    payment_method?: PaymentMethod | null;
    condition: OrderCondition;
    delivery_type: DeliveryType;
    delivery_period: DeliveryPeriod | null;
    delivery_date: string | null;
    delivery_time: string | null;
    delivery_address: string | null;
    notes: string | null;
    items_subtotal: number;
    items_cooked_subtotal?: number | null;
    raw_discount?: number | null;
    items_base_subtotal: number | null;
    cooking_surcharge: number | null;
    toppings_subtotal: number;
    shipping_fee: number;
    combined_dozen_savings: number;
    discount_type?: DiscountType | null;
    discount_value?: number | null;
    discount_amount?: number;
    rounding_adjustment?: number;
    total: number;
    version?: number;
    items?: Array<{
        product_id: number | null;
        product_name: string | null;
        pricing_mode: string;
        quantity: number;
        units_count: number;
        unit_price: number;
        subtotal: number;
        cooked_unit_price?: number | null;
        cooked_subtotal?: number | null;
        raw_discount?: number | null;
        base_unit_price?: number | null;
        base_subtotal?: number | null;
        cooking_surcharge?: number | null;
        regular_price_total: number;
        savings: number;
        composition: Array<{ product_name: string; quantity: number }>;
    }>;
    toppings?: Array<{
        name: string;
        quantity: number;
        unit_price: number;
        subtotal: number;
    }>;
}
export interface PaginatedOrders {
    data: AdminOrder[];
    current_page: number;
    last_page: number;
    prev_page_url: string | null;
    next_page_url: string | null;
    total: number;
}

export interface OrderInsightsProduct {
    product_id: number;
    name: string;
    units_count: number;
    image_url: string | null;
}

export interface OrderInsightsDeliveryType {
    type: DeliveryType;
    orders_count: number;
}

export interface OrderInsightsCondition {
    condition: OrderCondition;
    orders_count: number;
}
