import type { InventoryUnit, Pagination } from '../types';
export interface RecipeItem {
    id: number;
    name: string;
    kind: string | null;
    unit_family: string;
    default_input_unit_id: number;
    category: { id: number; name: string };
    base_unit: InventoryUnit;
    reference_mass_grams_per_unit: string | null;
}
export interface RecipeOptions {
    items: RecipeItem[];
    outputItems: RecipeItem[];
    units: InventoryUnit[];
}
export interface CurrentCost {
    status: string;
    known_cost: number;
    estimated_total_cost: number | null;
    estimated_cost_per_output_unit: number | null;
    coverage: {
        known_components: number;
        total_components: number;
        percentage_bps: number;
    };
    ingredients: {
        recipe_ingredient_id: number;
        estimated_cost: number | null;
        fallback: string | null;
        valuation_coverage: string;
    }[];
}
export interface RecipeVersion {
    id: number;
    recipe_id: number;
    version: number;
    recipe_name_snapshot: string;
    is_active: boolean;
    is_sealed: boolean;
    output_inventory_item_id: number;
    output_unit_id: number;
    expected_output_quantity: string;
    expected_output_base_quantity: string;
    output_item: RecipeItem;
    output_unit: InventoryUnit;
    current_cost: CurrentCost;
    ingredients: {
        id: number;
        inventory_item_id: number;
        unit_id: number;
        quantity: string;
        base_quantity: string;
        item: RecipeItem;
        unit: InventoryUnit;
        reference_mass_grams?: string;
    }[];
    theoretical_mass?: {
        grams: string;
        comparable_count: number;
        total_count: number;
    };
    recipe?: { id: number; name: string };
}
export interface Recipe {
    id: number;
    name: string;
    is_active: boolean;
    versions_count?: number;
}
export interface ProductionBatch {
    id: number;
    recipe_version: RecipeVersion;
    output_item: RecipeItem;
    expected_output_base_quantity: string;
    actual_output_quantity: string;
    actual_output_base_quantity: string;
    output_unit: InventoryUnit;
    effective_date: string;
    total_cost: number | null;
    cost_coverage: string;
    historical_cost_per_output_unit: number | null;
    operational_warnings: { inventory_item_id: number; code: string }[];
    inventory_transaction_id: number;
    reversal: { id: number; reason: string; effective_date: string } | null;
    transaction: {
        movements: {
            id: number;
            inventory_item_id: number;
            quantity: string;
            item: RecipeItem;
            cost_layer: { total_cost: number } | null;
        }[];
    };
    allocations: {
        id: number;
        allocated_quantity: string;
        allocated_cost: number | null;
        basis_quantity: string;
        basis_total_cost: number;
        method: string;
        fallback: string | null;
        reversal_of_id: number | null;
    }[];
}
export type RecipePage = Pagination<RecipeVersion>;
