export type PrintLine = {
    name: string;
    quantity: number;
    detail?: string | null;
    unit_price?: number;
    total?: number;
};

export type KitchenTicketPayload = {
    title: string;
    order: string;
    round: number | null;
    void: boolean;
    date: string;
    time: string;
    waiter: string | null;
    added_by: string | null;
    customer?: string | null;
    condition?: string | null;
    schedule?: string | null;
    delivery_date?: string | null;
    note: string | null;
    lines: PrintLine[];
};

export type BillLine = {
    name: string;
    /** Packs a line is priced by ("1 docena"); null for unit prices. */
    detail: string | null;
    /** Units the customer received. */
    quantity: number;
    /** Price per pack when `detail` is set, otherwise per unit. */
    unit_price: number;
    total: number;
};

export type BillPayload = {
    title: string;
    order: string;
    date: string;
    time: string;
    waiter: string;
    lines: BillLine[];
    subtotal: number;
    discount: number;
    rounding: number;
    total: number;
    legend: string;
};

type JobBase = {
    id: number;
    status: 'pending' | 'claimed' | 'printed';
    claim_token?: string;
    reprint: boolean;
    attempts: number;
    created_at: string;
    claimed_at: string | null;
    printed_at: string | null;
};

export type PrintJob =
    | (JobBase & { kind: 'kitchen_ticket'; payload: KitchenTicketPayload })
    | (JobBase & { kind: 'bill'; payload: BillPayload });

export type QueueStatus = {
    pending: number;
    oldest_pending_seconds: number | null;
    unconfirmed: number;
    oldest_unconfirmed_seconds: number | null;
};
