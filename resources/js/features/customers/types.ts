export interface CustomerOrderSummary {
    id: string;
    date: string;
    status: string;
    total: number;
}

export interface Customer {
    id: number;
    name: string;
    phone: string | null;
    address: string | null;
    notes: string | null;
    historical_spend: number;
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
