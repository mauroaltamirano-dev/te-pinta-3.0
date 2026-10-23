import type { Permission } from '@/types/auth';

/**
 * Single source of the admin information architecture: 9 entries in 6 groups.
 * Visibility mirrors the route permissions, so the menu never shows a page the
 * backend rejects. Only type imports (erased by Node) and no React, so
 * node:test can load it directly.
 */

export type AdminNavigationIcon =
    | 'sell'
    | 'orders'
    | 'customers'
    | 'dashboard'
    | 'products'
    | 'marketing'
    | 'inventory'
    | 'management'
    | 'settings';

export interface AdminNavigationSection {
    label: string;
    href: string;
    permission: Permission;
    /** Path prefixes that belong to this section besides `href`. */
    paths?: string[];
    /** `href` itself matches only exactly, not its nested pages. */
    exact?: boolean;
}

export interface AdminNavigationEntry {
    label: string;
    href: string;
    icon: AdminNavigationIcon;
    /** Without a permission the entry is visible when any section is. */
    permission?: Permission;
    /** Path prefixes that keep the entry active. */
    paths: string[];
    /** `href` matches only exactly (`/admin` is the POS, not every admin page); other paths match their nested pages. */
    exact?: boolean;
    /** Shown in the phone bottom bar instead of the "Más" sheet. */
    mobilePrimary?: boolean;
    sections?: AdminNavigationSection[];
}

export interface AdminNavigationGroup {
    label: string;
    entries: AdminNavigationEntry[];
}

const navigation: AdminNavigationGroup[] = [
    {
        label: 'Operación',
        entries: [
            {
                label: 'Vender',
                href: '/admin',
                icon: 'sell',
                // Roles without orders.manage are redirected to the order list.
                permission: 'orders.manage',
                paths: ['/admin', '/admin/tables', '/admin/print-station'],
                exact: true,
                mobilePrimary: true,
                sections: [
                    {
                        label: 'Mostrador',
                        href: '/admin',
                        permission: 'orders.manage',
                        exact: true,
                    },
                    {
                        label: 'Mesas',
                        href: '/admin/tables',
                        permission: 'orders.manage',
                    },
                    {
                        label: 'Impresión',
                        href: '/admin/print-station',
                        permission: 'orders.manage',
                    },
                ],
            },
            {
                label: 'Pedidos',
                href: '/admin/orders',
                icon: 'orders',
                permission: 'orders.view',
                paths: ['/admin/orders'],
                mobilePrimary: true,
            },
            {
                label: 'Clientes',
                href: '/admin/customers',
                icon: 'customers',
                permission: 'customers.view',
                paths: ['/admin/customers'],
            },
        ],
    },
    {
        label: 'Resumen',
        entries: [
            {
                label: 'Panel',
                href: '/admin/dashboard',
                icon: 'dashboard',
                permission: 'dashboard.view',
                paths: ['/admin/dashboard'],
                mobilePrimary: true,
            },
        ],
    },
    {
        label: 'Catálogo',
        entries: [
            {
                label: 'Productos',
                href: '/admin/settings/products',
                icon: 'products',
                permission: 'catalog.view',
                paths: ['/admin/settings/products'],
            },
            {
                label: 'Marketing',
                href: '/admin/marketing',
                icon: 'marketing',
                permission: 'marketing.manage',
                paths: ['/admin/marketing'],
                sections: [
                    {
                        label: 'Producto destacado',
                        href: '/admin/marketing',
                        permission: 'marketing.manage',
                        exact: true,
                        paths: ['/admin/marketing/products'],
                    },
                    {
                        label: 'Instagram',
                        href: '/admin/marketing/instagram',
                        permission: 'marketing.manage',
                    },
                ],
            },
        ],
    },
    {
        label: 'Stock y costos',
        entries: [
            {
                label: 'Inventario',
                href: '/admin/inventory',
                icon: 'inventory',
                permission: 'inventory.view',
                paths: ['/admin/inventory'],
                sections: [
                    {
                        label: 'Insumos',
                        href: '/admin/inventory',
                        permission: 'inventory.view',
                        exact: true,
                    },
                    {
                        label: 'Recetas',
                        href: '/admin/inventory/recipes',
                        permission: 'inventory.view',
                    },
                    {
                        label: 'Producción',
                        href: '/admin/inventory/production',
                        permission: 'inventory.view',
                    },
                    {
                        label: 'Ajustes de stock',
                        href: '/admin/inventory/adjustments',
                        permission: 'inventory.view',
                    },
                    {
                        label: 'Costos',
                        href: '/admin/inventory/costing',
                        permission: 'inventory.view',
                        // Packaging y gastos (costing policies) live inside Costos.
                        paths: ['/admin/inventory/costing-policies'],
                    },
                ],
            },
        ],
    },
    {
        label: 'Finanzas',
        entries: [
            {
                label: 'Gestión',
                href: '/admin/management',
                icon: 'management',
                permission: 'finance.view',
                paths: ['/admin/management'],
            },
        ],
    },
    {
        label: 'Configuración',
        entries: [
            {
                label: 'Configuración',
                href: '/admin/settings/general',
                icon: 'settings',
                paths: [
                    '/admin/settings/general',
                    '/admin/restaurant/floor',
                    '/admin/users',
                ],
                sections: [
                    {
                        label: 'General',
                        href: '/admin/settings/general',
                        permission: 'catalog.manage',
                    },
                    {
                        label: 'Plano del salón',
                        href: '/admin/restaurant/floor',
                        // The route also opens read-only for orders.manage.
                        permission: 'floor.manage',
                    },
                    {
                        label: 'Usuarios',
                        href: '/admin/users',
                        permission: 'users.manage',
                    },
                ],
            },
        ],
    },
];

export function visibleAdminNavigation(
    permissions: readonly string[],
): AdminNavigationGroup[] {
    const allowed = (permission: Permission) =>
        permissions.includes(permission);

    return navigation
        .map((group) => ({
            ...group,
            entries: group.entries.flatMap((entry) => {
                const sections = entry.sections?.filter(({ permission }) =>
                    allowed(permission),
                );

                if (
                    entry.permission
                        ? !allowed(entry.permission)
                        : !sections?.length
                ) {
                    return [];
                }

                return [
                    {
                        ...entry,
                        href: entry.permission
                            ? entry.href
                            : (sections?.[0]?.href ?? entry.href),
                        sections,
                    },
                ];
            }),
        }))
        .filter((group) => group.entries.length > 0);
}

export function matchesAdminPath(url: string, path: string, exact = false) {
    const pathname = url.split(/[?#]/)[0];

    return pathname === path || (!exact && pathname.startsWith(`${path}/`));
}

export function activeAdminEntry(
    groups: AdminNavigationGroup[],
    url: string,
): AdminNavigationEntry | undefined {
    return groups
        .flatMap((group) => group.entries)
        .find((entry) =>
            entry.paths.some((path) =>
                matchesAdminPath(url, path, entry.exact && path === entry.href),
            ),
        );
}

export function activeAdminSection(
    entry: AdminNavigationEntry | undefined,
    url: string,
): AdminNavigationSection | undefined {
    return entry?.sections?.find(
        (section) =>
            matchesAdminPath(url, section.href, section.exact) ||
            section.paths?.some((path) => matchesAdminPath(url, path)),
    );
}

export function adminMobilePrimaryEntries(
    groups: AdminNavigationGroup[],
): AdminNavigationEntry[] {
    return groups
        .flatMap((group) => group.entries)
        .filter((entry) => entry.mobilePrimary);
}

/** Breadcrumbs start at Panel, but only for users who may open it. */
export function adminBreadcrumbRoot(
    permissions: readonly string[],
): { title: string; href: string }[] {
    return permissions.includes('dashboard.view')
        ? [{ title: 'Panel', href: '/admin/dashboard' }]
        : [];
}

/**
 * The POS is the one "Nuevo pedido" flow; the long form stays as "Modo
 * avanzado". Both need orders.manage, like their routes.
 */
export function adminNewOrder(
    permissions: readonly string[],
): { href: string; advancedHref: string } | null {
    return permissions.includes('orders.manage')
        ? { href: '/admin', advancedHref: '/admin/orders/create' }
        : null;
}
