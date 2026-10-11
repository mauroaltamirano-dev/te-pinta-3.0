export type UnitFamily = 'weight' | 'volume' | 'count';

export interface InventoryUnit {
    id: number;
    code: string;
    name: string;
    symbol: string;
    family: UnitFamily;
    factor_to_base: string;
    is_base: boolean;
}

export interface InventoryCategory {
    id: number;
    name: string;
    is_active: boolean;
}

export interface PurchasePresentation {
    id: number;
    inventory_item_id: number;
    name: string;
    content_quantity: string;
    content_unit_id: number;
    content_unit: InventoryUnit;
    is_active: boolean;
}

export interface InventoryItem {
    id: number;
    category_id: number;
    category: InventoryCategory;
    name: string;
    tracks_stock: boolean;
    unit_family: UnitFamily;
    base_unit_id: number;
    default_input_unit_id: number;
    quantity_precision: number;
    is_active: boolean;
    kind: string | null;
    classification_pending: boolean;
    recipe_input_enabled: boolean;
    production_output_enabled: boolean;
    order_consumption_enabled: boolean;
    reference_mass_grams_per_unit: string | null;
    base_unit: InventoryUnit;
    default_input_unit: InventoryUnit;
    stock_quantity: string;
    presentations: PurchasePresentation[];
    valuation: {
        coverage:
            | 'zero'
            | 'fully_valued'
            | 'partial'
            | 'unknown'
            | 'negative'
            | 'unreliable';
        value: number | null;
        cost_quantity: string;
        cost_value: number;
    };
}

export interface InventoryTransaction {
    id: number;
    type: string;
    effective_date: string;
    source_type: string | null;
    source_id: string | null;
    description: string | null;
    movements: {
        id: number;
        quantity: string;
        inventory_item: { id: number; name: string; base_unit: InventoryUnit };
    }[];
}

export interface Pagination<T> {
    data: T[];
    current_page: number;
    last_page: number;
    total: number;
    per_page: number;
}
