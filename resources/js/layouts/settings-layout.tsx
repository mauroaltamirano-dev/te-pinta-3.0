import { Link, usePage } from '@inertiajs/react';
import { PackageOpen, Settings2 } from 'lucide-react';
import type { PropsWithChildren } from 'react';
import { cn } from '@/lib/utils';

const navigation = [
    {
        label: 'Gestión de productos',
        description: 'Categorías, precios y disponibilidad',
        icon: PackageOpen,
        href: '/admin/settings/products',
    },
    {
        label: 'Configuración general',
        description: 'Precios de pedidos y WhatsApp',
        icon: Settings2,
        href: '/admin/settings/general',
    },
] as const;

export default function SettingsLayout({ children }: PropsWithChildren) {
    const { url } = usePage();

    return (
        <section className="settings-workspace bg-transparent lg:h-full lg:min-h-0">
            <div className="grid min-h-[calc(100vh-10rem)] gap-6 lg:h-full lg:min-h-0 lg:grid-cols-[17rem_1fr]">
                <aside
                    data-settings-panel="navigation"
                    className="rounded-[0.5rem] bg-admin-surface lg:min-h-0 lg:overflow-y-auto"
                >
                    <nav
                        aria-label="Navegación de configuración"
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

                <div
                    data-settings-panel="content"
                    className="min-w-0 overflow-hidden rounded-[0.5rem] bg-admin-surface lg:min-h-0 lg:overflow-y-auto"
                >
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
