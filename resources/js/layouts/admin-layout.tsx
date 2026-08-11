import { Link, router, usePage } from '@inertiajs/react';
import {
    BarChart3,
    Boxes,
    LayoutDashboard,
    LogOut,
    Settings,
    ShoppingBasket,
    Users,
} from 'lucide-react';
import type { PropsWithChildren } from 'react';
import { Button } from '@/components/ui/button';

const navigation = [
    { label: 'Dashboard', href: '/admin', icon: LayoutDashboard },
    { label: 'Orders', href: '/admin/orders', icon: ShoppingBasket },
    { label: 'Customers', href: '/admin/customers', icon: Users },
    { label: 'Analytics', href: '/admin/analytics', icon: BarChart3 },
    { label: 'Inventory', href: '/admin/inventory', icon: Boxes },
    { label: 'Settings', href: '/admin/settings', icon: Settings },
];

export default function AdminLayout({ children }: PropsWithChildren) {
    const { url } = usePage();

    return (
        <div className="dark admin-shell min-h-screen bg-background text-foreground md:grid md:grid-cols-[16rem_1fr]">
            <aside className="border-b border-sidebar-border bg-sidebar px-4 py-4 md:min-h-screen md:border-r md:border-b-0">
                <div className="flex items-center justify-between md:block">
                    <Link
                        href="/admin"
                        className="font-display text-xl font-bold text-sidebar-foreground"
                    >
                        Te Pinta POS
                    </Link>
                    <Button
                        variant="ghost"
                        size="icon"
                        onClick={() => router.post('/logout')}
                        aria-label="Log out"
                        className="md:hidden"
                    >
                        <LogOut />
                    </Button>
                </div>
                <nav
                    className="mt-4 flex gap-1 overflow-x-auto pb-1 md:flex-col"
                    aria-label="Administration navigation"
                >
                    {navigation.map(({ label, href, icon: Icon }) => {
                        const active =
                            href === '/admin'
                                ? url === href
                                : url === href || url.startsWith(`${href}/`);

                        return (
                            <Link
                                key={href}
                                href={href}
                                aria-current={active ? 'page' : undefined}
                                className="flex shrink-0 items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium text-sidebar-foreground/65 transition-colors hover:bg-sidebar-accent hover:text-sidebar-accent-foreground aria-[current=page]:bg-sidebar-primary aria-[current=page]:text-sidebar-primary-foreground"
                            >
                                <Icon />
                                {label}
                            </Link>
                        );
                    })}
                </nav>
                <Button
                    variant="ghost"
                    className="mt-8 hidden w-full justify-start md:flex"
                    onClick={() => router.post('/logout')}
                >
                    <LogOut data-icon="inline-start" />
                    Log out
                </Button>
            </aside>
            <div className="min-w-0">
                <header className="border-b border-border bg-background/90 px-5 py-4 backdrop-blur sm:px-8">
                    <p className="text-xs font-semibold tracking-[0.2em] text-muted-foreground uppercase">
                        Administration
                    </p>
                </header>
                <main className="p-5 sm:p-8">{children}</main>
            </div>
        </div>
    );
}
