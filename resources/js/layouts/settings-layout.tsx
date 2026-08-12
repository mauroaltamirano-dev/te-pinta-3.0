import { Link, usePage } from '@inertiajs/react';
import { PackageOpen, Settings2 } from 'lucide-react';
import type { PropsWithChildren } from 'react';
import { cn } from '@/lib/utils';

const navigation = [
    {
        label: 'Product management',
        description: 'Categories, prices and availability',
        icon: PackageOpen,
        href: '/admin/settings/products',
    },
    {
        label: 'General configuration',
        description: 'Not available yet',
        icon: Settings2,
        href: null,
    },
] as const;

export default function SettingsLayout({ children }: PropsWithChildren) {
    const { url } = usePage();

    return (
        <section className="settings-workspace bg-transparent">
            <div className="grid min-h-[calc(100vh-10rem)] gap-6 lg:grid-cols-[17rem_1fr]">
                <aside className="rounded-[0.5rem] bg-admin-surface">
                    <nav
                        aria-label="Settings navigation"
                        className="flex gap-1 overflow-x-auto p-3 lg:flex-col lg:p-4"
                    >
                        {navigation.map(
                            ({ label, description, icon: Icon, href }) => {
                                const active =
                                    href !== null &&
                                    (url === href ||
                                        url.startsWith(`${href}/`));
                                const className = cn(
                                    'relative flex min-w-52 items-center gap-3 rounded-[0.5rem] px-3 py-3 text-left lg:min-w-0',
                                    active
                                        ? 'bg-admin-selection text-admin-text-primary after:absolute after:top-1/2 after:right-0 after:h-[2.4375rem] after:w-1 after:-translate-y-1/2 after:rounded-[1.875rem] after:bg-admin-accent'
                                        : href
                                          ? 'text-admin-text-secondary'
                                          : 'cursor-not-allowed text-admin-text-secondary/60',
                                );

                                if (!href) {
                                    return (
                                        <span
                                            key={label}
                                            aria-disabled="true"
                                            className={className}
                                        >
                                            <Icon />
                                            <NavigationText
                                                label={label}
                                                description={description}
                                            />
                                        </span>
                                    );
                                }

                                return (
                                    <Link
                                        key={label}
                                        href={href}
                                        aria-current={
                                            active ? 'page' : undefined
                                        }
                                        className={className}
                                    >
                                        <Icon
                                            className={cn(
                                                active && 'text-admin-accent',
                                            )}
                                        />
                                        <NavigationText
                                            label={label}
                                            description={description}
                                        />
                                    </Link>
                                );
                            },
                        )}
                    </nav>
                </aside>

                <div className="min-w-0 overflow-hidden rounded-[0.5rem] bg-admin-surface">
                    {children}
                </div>
            </div>
        </section>
    );
}

function NavigationText({
    label,
    description,
}: {
    label: string;
    description: string;
}) {
    return (
        <span className="min-w-0">
            <span className="block text-sm font-semibold">{label}</span>
            <span className="mt-0.5 block truncate text-xs text-admin-text-secondary">
                {description}
            </span>
        </span>
    );
}
