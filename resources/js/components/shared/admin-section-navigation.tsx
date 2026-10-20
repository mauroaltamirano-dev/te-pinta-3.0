import { Link } from '@inertiajs/react';
import { useEffect, useRef } from 'react';

interface NavigationItem {
    label: string;
    href: string;
}

export function AdminSectionNavigation({
    label,
    items,
    currentUrl,
    activeHref,
}: {
    label: string;
    items: NavigationItem[];
    currentUrl: string;
    /** Overrides prefix matching; null marks no section as current. */
    activeHref?: string | null;
}) {
    const current = new URL(currentUrl, 'http://localhost');
    const matched = items
        .filter(({ href }) => {
            const target = new URL(href, current);

            return (
                (current.pathname === target.pathname ||
                    current.pathname.startsWith(`${target.pathname}/`)) &&
                [...target.searchParams].every(([key, value]) =>
                    current.searchParams.getAll(key).includes(value),
                )
            );
        })
        .sort((a, b) => b.href.length - a.href.length)[0]?.href;
    const active = activeHref === undefined ? matched : activeHref;
    const navigation = useRef<HTMLElement>(null);

    useEffect(() => {
        const container = navigation.current;
        const selected = container?.querySelector('[aria-current="page"]');

        if (!container || !selected) {
            return;
        }

        const outer = container.getBoundingClientRect();
        const inner = selected.getBoundingClientRect();

        // Keep the active destination reachable without moving the page vertically.
        if (inner.left < outer.left) {
            container.scrollLeft -= outer.left - inner.left;
        } else if (inner.right > outer.right) {
            container.scrollLeft += inner.right - outer.right;
        }
    }, [active]);

    return (
        <nav
            ref={navigation}
            aria-label={label}
            className="admin-ui admin-section-navigation"
        >
            {items.map(({ href, label: itemLabel }) => (
                <Link
                    key={href}
                    href={href}
                    aria-current={href === active ? 'page' : undefined}
                >
                    {itemLabel}
                </Link>
            ))}
        </nav>
    );
}
