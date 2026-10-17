export type User = {
    id: number;
    name: string;
    email: string;
    avatar?: string;
    email_verified_at: string | null;
    two_factor_enabled?: boolean;
    created_at: string;
    updated_at: string;
    [key: string]: unknown;
};

export type Permission =
    | 'dashboard.view'
    | 'orders.view'
    | 'orders.manage'
    | 'customers.view'
    | 'customers.manage'
    | 'catalog.view'
    | 'catalog.manage'
    | 'inventory.view'
    | 'inventory.manage'
    | 'finance.view'
    | 'finance.manage'
    | 'marketing.manage'
    | 'floor.manage'
    | 'settings.manage'
    | 'users.manage';

export type Role =
    'owner' | 'manager' | 'cashier' | 'waiter' | 'kitchen' | 'accountant';

export type Auth = {
    user: User;
    role: Role | null;
    permissions: Permission[];
};

/* @chisel-passkeys */
export type Passkey = {
    id: number;
    name: string;
    authenticator: string | null;
    created_at_diff: string;
    last_used_at_diff: string | null;
};
/* @end-chisel-passkeys */

export type TwoFactorSetupData = {
    svg: string;
    url: string;
};

export type TwoFactorSecretKey = {
    secretKey: string;
};
