export type ManagementTransactionType =
    | 'opening'
    | 'adjustment_increase'
    | 'adjustment_decrease'
    | 'manual_income'
    | 'manual_expense'
    | 'management_outflow'
    | 'transfer'
    | 'distribution'
    | 'order_payment'
    | 'reversal';

export type ManagementOutflowType =
    'purchase' | 'expense' | 'personal_withdrawal';

export interface ManagementOutflowCategory {
    id: number;
    code: string;
    name: string;
    type: ManagementOutflowType;
    is_active: boolean;
}

export interface ManagementOutflow {
    id: number;
    type: ManagementOutflowType;
    type_label?: string;
    category: Pick<ManagementOutflowCategory, 'id' | 'code' | 'name'>;
    description: string;
    counterparty: string | null;
    amount: number;
    effective_date: string;
    created_at: string;
    is_reversal?: boolean;
    reversed: boolean;
    status?: 'active' | 'reversed';
}

export interface ManagementTransfer {
    id: number;
    description: string | null;
    amount: number;
    effective_date: string;
    created_at: string;
    is_reversal: boolean;
    reversed: boolean;
    source_wallet: {
        id: number;
        name: string;
        archived_at: string | null;
    };
    destination_wallet: {
        id: number;
        name: string;
        archived_at: string | null;
    };
}

export interface DistributionAllocation {
    id?: number;
    wallet_id?: number;
    wallet: {
        id: number;
        name: string;
        archived_at: string | null;
    };
    wallet_name?: string;
    percentage_bps: number;
    amount?: number;
    sort_order: number;
}

export interface DistributionRuleVersion {
    id: number;
    version_number: number;
    created_at?: string;
    allocations: DistributionAllocation[];
}

export interface DistributionRule {
    id: number;
    name: string;
    active?: boolean;
    is_active?: boolean;
    current_version?: DistributionRuleVersion | null;
    latest_version?: DistributionRuleVersion | null;
    versions: DistributionRuleVersion[];
}

export interface ManagementDistribution {
    id: number;
    amount: number;
    effective_date: string;
    description: string | null;
    created_at: string;
    is_reversal?: boolean;
    reversed: boolean;
    rule_name?: string;
    rule_version_number?: number;
    rule?: Pick<DistributionRule, 'id' | 'name'>;
    rule_version?: Pick<DistributionRuleVersion, 'id' | 'version_number'>;
    source_wallet: {
        id: number;
        name: string;
        archived_at: string | null;
    };
    management_order_payment: {
        id: number;
        financial_transaction_id: number;
        order: {
            id: number;
            public_id: string;
        };
    } | null;
    allocations: DistributionAllocation[];
}

export interface ManagementOrderPayment {
    id: number;
    order: {
        id: number;
        public_id: string;
        customer_name: string;
    };
    destination_wallet: {
        id: number;
        name: string;
        archived_at: string | null;
    };
    amount: number;
    effective_date: string;
    distribution_status: 'pending' | 'distributed' | 'reversed';
    distribution: {
        id: number;
        financial_transaction_id: number;
        rule: Pick<DistributionRule, 'id' | 'name'>;
        rule_version: Pick<DistributionRuleVersion, 'id' | 'version_number'>;
        reversed: boolean;
    } | null;
    is_reversal: boolean;
    reversed: boolean;
}

export interface ManagementWallet {
    id: number;
    name: string;
    sort_order: number;
    balance: number;
    archived_at: string | null;
    is_order_payment_default: boolean;
}

export interface ManagementAdministrator {
    id: number;
    name: string;
}

export interface ManagementAttachment {
    id: number;
    name: string;
    mime_type: string;
    size: number;
    created_at: string;
    uploaded_by: ManagementAdministrator;
    preview_url: string | null;
    download_url: string;
}

export interface ManagementTransaction {
    id: number;
    type: ManagementTransactionType;
    description: string | null;
    effective_date: string;
    created_at: string;
    created_by: ManagementAdministrator;
    reversal_of_id: number | null;
    reversal_transaction_id: number | null;
    attachments_count: number;
    management_outflow: ManagementOutflow | null;
    management_transfer: ManagementTransfer | null;
    management_distribution: ManagementDistribution | null;
    management_order_payment: ManagementOrderPayment | null;
    entries: Array<{
        id: number;
        amount: number;
        wallet: {
            id: number;
            name: string;
            archived_at: string | null;
        };
    }>;
}

export interface PaginatedManagementTransactions {
    data: ManagementTransaction[];
    current_page: number;
    last_page: number;
    prev_page_url: string | null;
    next_page_url: string | null;
    total: number;
}

export interface ManagementMetrics {
    total_balance: number;
    period_income: number;
    period_operating_outflow: number;
    period_personal_withdrawal: number;
    period_distribution: number;
    period_expense?: number;
}

export interface ManagementFilters {
    wallet_id: string | number | null;
    type: ManagementTransactionType | ManagementOutflowType | null;
    category_id: string | number | null;
    from: string;
    to: string;
}

export interface ManagementMovementTypeOption {
    value: Exclude<ManagementTransactionType, 'reversal'>;
    label: string;
}

export interface ManagementOutflowTypeOption {
    value: ManagementOutflowType;
    label: string;
}

export interface ManagementPageProps {
    wallets: ManagementWallet[];
    transactions: PaginatedManagementTransactions;
    summary: ManagementMetrics;
    filters: ManagementFilters;
    movement_types: ManagementMovementTypeOption[];
    outflow_types: ManagementOutflowTypeOption[];
    outflow_categories: ManagementOutflowCategory[];
    distribution_rules: DistributionRule[];
}

export type ManagementAuditSeverity = 'info' | 'warning' | 'critical';

export type ManagementAuditFindingType =
    | 'pending_distribution'
    | 'reversed_payment'
    | 'reversed_distribution'
    | 'missing_semantic_source'
    | 'amount_mismatch'
    | 'wallet_mismatch'
    | 'date_mismatch'
    | 'ledger_structure_mismatch'
    | 'missing_active_distribution_rule'
    | 'multiple_active_distribution_rules'
    | 'missing_default_income_wallet'
    | 'multiple_default_income_wallets'
    | 'archived_default_income_wallet';

export interface ManagementAuditFinding {
    key: string;
    type: ManagementAuditFindingType;
    severity: ManagementAuditSeverity;
    title: string;
    description: string;
    status?: string | null;
    entity_type: string | null;
    entity_id: number | null;
    entity_label: string | null;
    financial_transaction_id?: number | null;
    management_order_payment_id?: number | null;
    order_public_id?: string | null;
    amount?: number | null;
    expected?: string | number | null;
    found?: string | number | null;
    effective_date?: string | null;
    age_days?: number | null;
    wallet?: {
        id: number;
        name: string;
        archived_at: string | null;
    } | null;
}

export interface ManagementAuditWalletBalance {
    wallet_id: number;
    wallet_name: string;
    derived_balance: number;
    transaction_count: number;
    last_transaction_at: string | null;
    archived_at?: string | null;
    status: 'ok' | 'warning' | 'critical';
}

export interface ManagementAuditFilters {
    period: 'today' | '7_days' | '30_days' | 'custom';
    from: string;
    to: string;
    wallet_id: string | number | null;
    type: string | null;
    severity: ManagementAuditSeverity | null;
    status: string | null;
}

export interface ManagementAuditOption {
    value: string;
    label: string;
}

export interface ManagementAuditData {
    summary: {
        ok: number;
        pending: number;
        warning: number;
        critical: number;
    };
    findings: {
        data: ManagementAuditFinding[];
        current_page: number;
        last_page: number;
        prev_page_url: string | null;
        next_page_url: string | null;
        total: number;
    };
    wallet_balances: ManagementAuditWalletBalance[];
    filters: ManagementAuditFilters;
    options: {
        wallets: Array<{ value: string | number; label: string }>;
        types: ManagementAuditOption[];
        severities: ManagementAuditOption[];
        statuses: ManagementAuditOption[];
    };
}

export interface ManagementAuditPageProps {
    audit: ManagementAuditData;
}
