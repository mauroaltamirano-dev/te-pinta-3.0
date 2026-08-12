import { Link, router, usePage } from '@inertiajs/react';
import {
    BarChart3,
    Boxes,
    LayoutDashboard,
    LogOut,
    Settings,
    ShoppingBasket,
    Store,
    Users,
} from 'lucide-react';
import { Button } from '@/components/ui/button';
import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { cn } from '@/lib/utils';

const navigation = [
    { label: 'Dashboard', href: '/admin', icon: LayoutDashboard },
    { label: 'Pedidos', href: '/admin/orders', icon: ShoppingBasket },
    { label: 'Clientes', href: '/admin/customers', icon: Users },
    { label: 'Analíticas', href: '/admin/analytics', icon: BarChart3 },
    { label: 'Gestión', href: '/admin/inventory', icon: Boxes },
    { label: 'Configuración', href: '/admin/settings', icon: Settings },
] as const;

export function AdminSidebar() {
    const { url } = usePage();

    return (
        <aside className="admin-sidebar flex items-center gap-2 bg-admin-surface p-2 md:sticky md:top-0 md:flex md:h-screen md:w-[6.5rem] md:flex-col md:items-start md:gap-0 md:rounded-r-2xl md:p-0">
            <Tooltip>
                <TooltipTrigger asChild>
                    <Link
                        href="/admin"
                        aria-label="Te Pinta POS"
                        className="flex size-11 shrink-0 items-center justify-center rounded-xl bg-admin-action text-admin-accent focus-visible:ring-2 focus-visible:ring-white focus-visible:outline-none md:mt-6 md:ml-6 md:size-14"
                    >
                        <Store
                            className="size-8 md:size-10"
                            strokeWidth={1.6}
                        />
                    </Link>
                </TooltipTrigger>
                <TooltipContent side="right">Te Pinta POS</TooltipContent>
            </Tooltip>

            <nav
                aria-label="Administration navigation"
                className="flex min-w-0 flex-1 overflow-x-auto md:mt-4 md:ml-3 md:h-[38.25rem] md:w-[5.75rem] md:flex-none md:flex-col md:overflow-visible"
            >
                {navigation.map(({ label, href, icon: Icon }) => {
                    const active =
                        href === '/admin'
                            ? url === href
                            : url === href || url.startsWith(`${href}/`);

                    return (
                        <div
                            key={href}
                            data-active={active}
                            className="admin-nav-slot relative flex size-12 shrink-0 items-center justify-center md:h-[6.375rem] md:w-[5.75rem] md:justify-start md:pl-3"
                        >
                            <Tooltip>
                                <TooltipTrigger asChild>
                                    <Link
                                        href={href}
                                        aria-label={label}
                                        aria-current={
                                            active ? 'page' : undefined
                                        }
                                        className={cn(
                                            'relative flex size-11 items-center justify-center rounded-[0.5rem] text-admin-accent transition-colors focus-visible:ring-2 focus-visible:ring-white focus-visible:outline-none md:size-14',
                                            active &&
                                                'bg-admin-accent text-admin-text-primary shadow-admin-nav-active',
                                        )}
                                    >
                                        <Icon
                                            className="size-6"
                                            strokeWidth={1.6}
                                        />
                                    </Link>
                                </TooltipTrigger>
                                <TooltipContent side="right">
                                    {label}
                                </TooltipContent>
                            </Tooltip>
                        </div>
                    );
                })}
            </nav>

            <Tooltip>
                <TooltipTrigger asChild>
                    <Button
                        type="button"
                        variant="ghost"
                        size="icon"
                        aria-label="Cerrar sesión"
                        onClick={() => router.post('/logout')}
                        className="size-11 shrink-0 rounded-[0.5rem] text-admin-accent hover:bg-transparent hover:text-admin-accent md:mt-auto md:mb-6 md:ml-6 md:size-14"
                    >
                        <LogOut className="size-6" strokeWidth={1.6} />
                    </Button>
                </TooltipTrigger>
                <TooltipContent side="right">Cerrar sesión</TooltipContent>
            </Tooltip>
        </aside>
    );
}
