import type { InventoryUnit } from '../types';
export interface AdjustmentItem {
    id: number;
    name: string;
    unit_family: string;
    default_input_unit_id: number;
    base_unit: InventoryUnit;
    category: { name: string };
}
export interface AdjustmentPreview {
    can_confirm: boolean;
    delta: string;
    current_stock: string;
    result_stock: string;
    cost_total: number | null;
    cost_status: string;
    cost_coverage: string;
    warnings: string[];
    current_coverage: string;
    current_value: number | null;
    result_coverage: string;
    result_value: number | null;
}
export interface Adjustment {
    id: number;
    type: string;
    quantity: string;
    delta: string;
    stock_before: string;
    physical_count: string | null;
    cost_total: number | null;
    cost_status: string;
    cost_coverage: string;
    cost_basis_quantity: string | null;
    cost_basis_total: number | null;
    cost_method: string | null;
    cost_fallback: string | null;
    warnings: string[];
    effective_date: string;
    created_at: string;
    note: string | null;
    item: AdjustmentItem;
    unit: InventoryUnit;
    creator: { name: string };
    inventory_transaction_id: number;
    reversal_of_id: number | null;
    reversal: Adjustment | null;
}
