export type PackagingComponent = {
    inventory_item_id: number;
    quantity: string;
    unit_id: number;
    item?: { name: string };
    unit?: { code: string };
};
export type PolicyPresentation = {
    commercial_quantity: number;
    packaging_mode: 'none' | 'components';
    components: PackagingComponent[];
};
export type PolicyPayload = {
    name: string;
    overhead_basis_points: number;
    is_active: boolean;
    presentations: PolicyPresentation[];
};
export type Policy = PolicyPayload & { id: number };
export type Coverage = {
    status: 'complete' | 'incomplete' | 'unreliable';
    known_components: number;
    total_components: number;
    percentage_bps: number | null;
};
export type CostLine = {
    inventory_item_id: number;
    item_name: string;
    quantity: string;
    unit_code: string;
    estimated_cost: number | null;
    valuation_coverage: string;
    warning: string | null;
};
export type EconomicsPresentation = {
    commercial_quantity: number;
    pricing_mode: 'unit' | 'half_dozen' | 'dozen';
    selling_price: number | null;
    status: Coverage['status'];
    direct_cost: number | null;
    direct_coverage: Coverage;
    packaging_status: 'none' | 'components' | 'missing';
    packaging_cost: number | null;
    packaging_coverage: Coverage;
    overhead_estimate: number | null;
    estimated_total_cost: number | null;
    estimated_margin: number | null;
    estimated_margin_percentage_bps: number | null;
    warnings: string[];
    direct_components: CostLine[];
    packaging_components: CostLine[];
};
export type Economics = {
    product_id: number;
    product_name: string;
    pricing_family: 'empanada' | 'unit';
    physical_status: 'missing_config' | 'no_inventory' | 'consume';
    policy_status: 'missing' | 'active' | 'inactive';
    costing_policy: {
        id: number;
        name: string;
        overhead_basis_points: number;
    } | null;
    presentations: EconomicsPresentation[];
};
export function percentage(bp: number | null): string {
    if (bp === null) {
        return 'No disponible';
    }

    const abs = Math.abs(bp);
    const fraction = String(abs % 100)
        .padStart(2, '0')
        .replace(/0+$/, '');

    return `${bp < 0 ? '-' : ''}${Math.trunc(abs / 100)}${fraction ? `,${fraction}` : ''} %`;
}
