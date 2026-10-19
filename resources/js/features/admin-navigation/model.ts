import type { Permission } from '@/types/auth';

/**
 * Single source of the admin information architecture: 9 entries in 6 groups.
 * Visibility mirrors the route permissions, so the menu never shows a page the
 * backend rejects. Kept free of React and path aliases so node:test can load it.
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
}

export interface AdminNavigationEntry {
    label: string;
    href: string;
    icon: AdminNavigationIcon;
    /** Without a permission the entry is visible when any section is. */
    permission?: Permission;
    /** Path prefixes that keep the entry active. */
    paths: string[];
    /** `/admin` is the POS only, not every admin page. */
    exact?: boolean;
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
                paths: ['/admin'],
                exact: true,
            },
            {
                label: 'Pedidos',
                href: '/admin/orders',
                icon: 'orders',
                permission: 'orders.view',
                paths: ['/admin/orders'],
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
                matchesAdminPath(url, path, entry.exact),
            ),
        );
}

const mobilePrimaryHrefs = ['/admin', '/admin/orders', '/admin/dashboard'];

export function adminMobilePrimaryEntries(
    groups: AdminNavigationGroup[],
): AdminNavigationEntry[] {
    return groups
        .flatMap((group) => group.entries)
        .filter((entry) => mobilePrimaryHrefs.includes(entry.href));
}
