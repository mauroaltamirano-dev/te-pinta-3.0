import type { InventoryItem, InventoryUnit } from '@/features/inventory/types';
export interface Purchase {
    id: number;
    supplier_name: string | null;
    description: string | null;
    effective_date: string;
    subtotal: number;
    shipping_amount: number;
    total: number;
    status: 'confirmed' | 'reversed';
    wallet: { id: number; name: string };
    financial_transaction: { id: number; attachments_count: number };
    inventory_transaction_id: number | null;
    lines: Array<{
        id: number;
        item_name: string;
        category_name: string;
        tracks_stock: boolean;
        quantity: string;
        input_unit_code: string | null;
        presentation_name: string | null;
        base_quantity: string;
        base_unit_code: string;
        line_total: number;
    }>;
    reversal: { description: string; effective_date: string } | null;
}
export interface PurchaseOptions {
    business_today: string;
    inventory_items: InventoryItem[];
    units: InventoryUnit[];
    wallets: Array<{ id: number; name: string }>;
}
