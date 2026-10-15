import { Link, router, usePage } from '@inertiajs/react';
import {
    BarChart3,
    Instagram,
    LayoutDashboard,
    LogOut,
    Megaphone,
    Menu,
    Package,
    Settings,
    ShoppingBasket,
    Store,
    Users,
    WalletCards,
} from 'lucide-react';
import { useState } from 'react';
import { createPortal } from 'react-dom';
import { BrandLogo } from '@/components/brand-logo';
import { Button } from '@/components/ui/button';
import { Sheet, SheetContent, SheetTitle } from '@/components/ui/sheet';
import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { cn } from '@/lib/utils';

const navigation = [
    { label: 'Inicio', href: '/admin', icon: Store },
    {
        label: 'Panel operativo',
        href: '/admin/dashboard',
        icon: LayoutDashboard,
    },
    { label: 'Pedidos', href: '/admin/orders', icon: ShoppingBasket },
    { label: 'Clientes', href: '/admin/customers', icon: Users },
    { label: 'Analíticas', href: '/admin/analytics', icon: BarChart3 },
    { label: 'Gestión', href: '/admin/management', icon: WalletCards },
    { label: 'Inventario', href: '/admin/inventory', icon: Package },
    { label: 'Costo actual', href: '/admin/inventory/costing', icon: Package },
    {
        label: 'Políticas de costo',
        href: '/admin/inventory/costing-policies',
        icon: Package,
    },
    { label: 'Recetas', href: '/admin/inventory/recipes', icon: Package },
    { label: 'Ajustes', href: '/admin/inventory/adjustments', icon: Package },
    { label: 'Producción', href: '/admin/inventory/production', icon: Package },
    {
        label: 'Plano del restaurante',
        href: '/admin/restaurant/floor',
        icon: Store,
    },
    { label: 'Marketing', href: '/admin/marketing', icon: Megaphone },
    {
        label: 'Instagram',
        href: '/admin/marketing/instagram',
        icon: Instagram,
    },
    { label: 'Configuración', href: '/admin/settings', icon: Settings },
] as const;

export function AdminSidebar() {
    const { url } = usePage();
    const [moreOpen, setMoreOpen] = useState(false);
    const primaryNavigation = navigation.filter(({ href }) =>
        ['/admin', '/admin/orders', '/admin/customers'].includes(href),
    );
    const secondaryNavigation = navigation.filter(
        ({ href }) => !primaryNavigation.some((item) => item.href === href),
    );
    const isActive = (href: string) =>
        href === '/admin'
            ? url === href
            : href === '/admin/marketing'
              ? url === href
              : url === href || url.startsWith(`${href}/`);

    const mobileNavigation =
        typeof document !== 'undefined'
            ? createPortal(
                  <>
                      <nav
                          aria-label="Navegación principal"
                          className="fixed inset-x-3 bottom-3 z-50 grid grid-cols-4 rounded-2xl border border-white/10 bg-admin-surface px-2 pt-2 pb-[max(0.5rem,env(safe-area-inset-bottom))] shadow-2xl md:hidden"
                      >
                          {primaryNavigation.map(
                              ({ label, href, icon: Icon }) => {
                                  const active = isActive(href);

                                  return (
                                      <Link
                                          key={href}
                                          href={href}
                                          aria-current={
                                              active ? 'page' : undefined
                                          }
                                          className={cn(
                                              'flex min-h-12 flex-col items-center justify-center gap-1 rounded-xl px-1 text-[0.6875rem] font-medium text-admin-text-tertiary transition-[color,background-color,transform] active:scale-[0.97]',
                                              active &&
                                                  'bg-admin-accent/15 text-admin-accent',
                                          )}
                                      >
                                          <Icon
                                              className="size-5"
                                              strokeWidth={1.8}
                                          />
                                          {label}
                                      </Link>
                                  );
                              },
                          )}
                          <button
                              type="button"
                              aria-label="Abrir más opciones"
                              aria-expanded={moreOpen}
                              onClick={() => setMoreOpen(true)}
                              className={cn(
                                  'flex min-h-12 flex-col items-center justify-center gap-1 rounded-xl px-1 text-[0.6875rem] font-medium text-admin-text-tertiary transition-[color,background-color,transform] active:scale-[0.97]',
                                  secondaryNavigation.some(({ href }) =>
                                      isActive(href),
                                  ) && 'bg-admin-accent/15 text-admin-accent',
                              )}
                          >
                              <Menu className="size-5" strokeWidth={1.8} />
                              Más
                          </button>
                      </nav>
                      <Sheet open={moreOpen} onOpenChange={setMoreOpen}>
                          <SheetContent
                              side="bottom"
                              overlayClassName="bg-black/70"
                              className="admin-shell dark gap-0 rounded-t-3xl border-admin-border bg-admin-surface px-4 pt-4 pb-[max(1rem,env(safe-area-inset-bottom))] md:hidden"
                          >
                              <SheetTitle className="px-2 pb-4 text-lg text-white">
                                  Más opciones
                              </SheetTitle>
                              <div className="grid grid-cols-2 gap-2">
                                  {secondaryNavigation.map(
                                      ({ label, href, icon: Icon }) => (
                                          <Link
                                              key={href}
                                              href={href}
                                              aria-current={
                                                  isActive(href)
                                                      ? 'page'
                                                      : undefined
                                              }
                                              onClick={() => setMoreOpen(false)}
                                              className={cn(
                                                  'flex min-h-14 items-center gap-3 rounded-xl bg-admin-surface-raised px-4 text-sm font-medium text-admin-text-secondary active:scale-[0.98]',
                                                  isActive(href) &&
                                                      'text-admin-accent ring-1 ring-admin-accent/30',
                                              )}
                                          >
                                              <Icon
                                                  className="size-5"
                                                  strokeWidth={1.7}
                                              />
                                              {label}
                                          </Link>
                                      ),
                                  )}
                              </div>
                              <Button
                                  type="button"
                                  variant="ghost"
                                  onClick={() => router.post('/logout')}
                                  className="mt-3 h-12 w-full justify-start text-admin-text-secondary"
                              >
                                  <LogOut /> Cerrar sesión
                              </Button>
                          </SheetContent>
                      </Sheet>
                  </>,
                  document.body,
              )
            : null;

    return (
        <>
            <aside className="admin-sidebar sticky top-0 hidden h-screen w-[6.5rem] flex-col items-start rounded-r-2xl bg-admin-surface md:col-start-1 md:row-start-1 md:flex">
                <Tooltip>
                    <TooltipTrigger asChild>
                        <Link
                            href="/admin"
                            aria-label="Te Pinta POS"
                            className="flex size-11 shrink-0 items-center justify-center overflow-hidden rounded-xl bg-admin-accent ring-1 ring-black/10 focus-visible:ring-2 focus-visible:ring-white focus-visible:outline-none md:mt-6 md:ml-6 md:size-14"
                        >
                            <BrandLogo alt="" className="size-full" />
                        </Link>
                    </TooltipTrigger>
                    <TooltipContent side="right">Te Pinta POS</TooltipContent>
                </Tooltip>

                <nav
                    aria-label="Navegación de administración"
                    className="flex min-w-0 flex-1 overflow-x-auto md:mt-4 md:ml-3 md:min-h-0 md:w-[5.75rem] md:flex-col md:overflow-visible"
                >
                    {navigation.map(({ label, href, icon: Icon }) => {
                        const active = isActive(href);

                        return (
                            <div
                                key={href}
                                data-active={active}
                                className="admin-nav-slot relative flex size-12 shrink-0 items-center justify-center md:min-h-14 md:w-[5.75rem] md:flex-1 md:justify-start md:pl-3"
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
            {mobileNavigation}
        </>
    );
}
