import { Link } from '@inertiajs/react';
import {
    Bell,
    CircleHelp,
    LockKeyhole,
    PackageOpen,
    Palette,
    Store,
} from 'lucide-react';
import { cn } from '@/lib/utils';

const items = [
    {
        label: 'Appearance',
        description: 'Theme and display',
        icon: Palette,
        available: false,
    },
    {
        label: 'Your restaurant',
        description: 'Business information',
        icon: Store,
        available: false,
    },
    {
        label: 'Product management',
        description: 'Categories, prices and availability',
        icon: PackageOpen,
        available: true,
    },
    {
        label: 'Notifications',
        description: 'Operational alerts',
        icon: Bell,
        available: false,
    },
    {
        label: 'Access & security',
        description: 'Authentication and access',
        icon: LockKeyhole,
        available: false,
    },
    {
        label: 'About',
        description: 'Application information',
        icon: CircleHelp,
        available: false,
    },
] as const;

export function SettingsSidebar() {
    return (
        <aside className="border-b border-border bg-sidebar/35 lg:border-r lg:border-b-0">
            <div className="flex gap-1 overflow-x-auto p-3 lg:flex-col lg:p-4">
                {items.map(({ label, description, icon: Icon, available }) => {
                    const className = cn(
                        'flex min-w-52 items-center gap-3 rounded-lg px-3 py-3 text-left transition-colors lg:min-w-0',
                        available
                            ? 'bg-primary/18 text-foreground ring-1 ring-primary/25'
                            : 'cursor-not-allowed text-muted-foreground/60',
                    );

                    if (available) {
                        return (
                            <Link
                                key={label}
                                href="/admin/settings/products"
                                aria-current="page"
                                className={className}
                            >
                                <Icon className="text-primary" />
                                <span className="min-w-0">
                                    <span className="block text-sm font-semibold">
                                        {label}
                                    </span>
                                    <span className="mt-0.5 block truncate text-xs text-muted-foreground">
                                        {description}
                                    </span>
                                </span>
                            </Link>
                        );
                    }

                    return (
                        <span
                            key={label}
                            className={className}
                            aria-disabled="true"
                            title="Not available yet"
                        >
                            <Icon />
                            <span className="min-w-0">
                                <span className="block text-sm font-medium">
                                    {label}
                                </span>
                                <span className="mt-0.5 block truncate text-xs">
                                    {description}
                                </span>
                            </span>
                        </span>
                    );
                })}
            </div>
        </aside>
    );
}
