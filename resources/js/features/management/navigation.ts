/**
 * Gestión sections: the ledger views of /admin/management (query `section`)
 * plus Compras and Auditoría, their own pages. "Registrar operación" is a
 * header action, not a tab. No imports, so node:test can load it directly.
 */

export type ManagementSection =
    'panorama' | 'operations' | 'wallets' | 'movements';

const ledgerSections = [
    { id: 'panorama', label: 'Panorama' },
    { id: 'movements', label: 'Movimientos' },
    { id: 'wallets', label: 'Billeteras' },
] as const;

const pageSections = [
    { label: 'Compras', href: '/admin/management/purchases' },
    { label: 'Auditoría', href: '/admin/management/audit' },
];

const sectionIds: readonly string[] = [
    ...ledgerSections.map(({ id }) => id),
    'operations',
];

// Movement filters open Movimientos when no section is requested.
const movementFilters = [
    'transaction_id',
    'type',
    'wallet_id',
    'category_id',
    'page',
    'history_period',
    'history_from',
    'history_to',
];

const parse = (url: string) => new URL(url, 'http://localhost');

const isLedger = (url: URL) => url.pathname === '/admin/management';

export function requestedManagementSection(url: string): ManagementSection {
    const query = parse(url).searchParams;
    const section = query.get('section');

    if (section && sectionIds.includes(section)) {
        return section as ManagementSection;
    }

    return movementFilters.some((key) => query.has(key))
        ? 'movements'
        : 'panorama';
}

/** Ledger links keep the reporting filters of the ledger page. */
export function managementSectionHref(
    url: string,
    section: ManagementSection,
): string {
    const current = parse(url);
    const query = isLedger(current)
        ? current.searchParams
        : new URLSearchParams();
    query.set('section', section);

    return `/admin/management?${query}`;
}

export function managementNavigation(url: string): {
    items: { label: string; href: string }[];
    currentHref: string | null;
} {
    const current = parse(url);
    const items = [
        ...ledgerSections.map(({ id, label }) => ({
            label,
            href: managementSectionHref(url, id),
        })),
        ...pageSections,
    ];

    if (isLedger(current)) {
        const section = requestedManagementSection(url);

        return {
            items,
            currentHref:
                section === 'operations'
                    ? null
                    : managementSectionHref(url, section),
        };
    }

    return {
        items,
        currentHref:
            pageSections.find(
                ({ href }) =>
                    current.pathname === href ||
                    current.pathname.startsWith(`${href}/`),
            )?.href ?? null,
    };
}
