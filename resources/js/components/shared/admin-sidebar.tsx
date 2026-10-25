import { Link, router, usePage } from '@inertiajs/react';
import {
    LayoutDashboard,
    LogOut,
    Megaphone,
    Menu,
    Package,
    Settings,
    ShoppingBasket,
    Store,
    Users,
    UtensilsCrossed,
    WalletCards,
} from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import { Fragment, useState } from 'react';
import { createPortal } from 'react-dom';
import { BrandLogo } from '@/components/brand-logo';
import { Button } from '@/components/ui/button';
import { Sheet, SheetContent, SheetTitle } from '@/components/ui/sheet';
import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import {
    activeAdminEntry,
    adminMobilePrimaryEntries,
    visibleAdminNavigation,
} from '@/features/admin-navigation/model';
import type { AdminNavigationIcon } from '@/features/admin-navigation/model';
import { useRestaurantIdentity } from '@/hooks/use-restaurant-identity';
import { cn } from '@/lib/utils';

const icons: Record<AdminNavigationIcon, LucideIcon> = {
    sell: Store,
    orders: ShoppingBasket,
    customers: Users,
    dashboard: LayoutDashboard,
    products: UtensilsCrossed,
    marketing: Megaphone,
    inventory: Package,
    management: WalletCards,
    settings: Settings,
};

export function AdminSidebar() {
    const { url, props } = usePage();
    const [moreOpen, setMoreOpen] = useState(false);
    const restaurant = useRestaurantIdentity();
    // Only modules the backend lets this user open; the routes enforce it.
    const groups = visibleAdminNavigation(props.auth?.permissions ?? []);
    const activeHref = activeAdminEntry(groups, url)?.href;
    const primaryNavigation = adminMobilePrimaryEntries(groups);
    const secondaryGroups = groups
        .map((group) => ({
            ...group,
            entries: group.entries.filter(
                (entry) => !primaryNavigation.includes(entry),
            ),
        }))
        .filter((group) => group.entries.length > 0);

    const mobileNavigation =
        typeof document !== 'undefined'
            ? createPortal(
                  <>
                      <nav
                          aria-label="Navegación principal"
                          style={{
                              gridTemplateColumns: `repeat(${primaryNavigation.length + 1}, minmax(0, 1fr))`,
                          }}
                          className="fixed inset-x-3 bottom-3 z-50 grid rounded-2xl border border-admin-border bg-admin-surface px-2 pt-2 pb-[max(0.5rem,env(safe-area-inset-bottom))] shadow-2xl md:hidden"
                      >
                          {primaryNavigation.map(({ label, href, icon }) => {
                              const Icon = icons[icon];
                              const active = href === activeHref;

                              return (
                                  <Link
                                      key={href}
                                      href={href}
                                      aria-current={active ? 'page' : undefined}
                                      className={cn(
                                          'flex min-h-12 flex-col items-center justify-center gap-1 rounded-xl px-1 text-[0.6875rem] font-medium text-admin-text-tertiary transition-[color,background-color,transform] active:scale-[0.97]',
                                          active &&
                                              'bg-admin-accent/15 text-admin-accent-text',
                                      )}
                                  >
                                      <Icon
                                          className="size-5"
                                          strokeWidth={1.8}
                                      />
                                      {label}
                                  </Link>
                              );
                          })}
                          <button
                              type="button"
                              aria-label="Abrir más opciones"
                              aria-expanded={moreOpen}
                              onClick={() => setMoreOpen(true)}
                              className={cn(
                                  'flex min-h-12 flex-col items-center justify-center gap-1 rounded-xl px-1 text-[0.6875rem] font-medium text-admin-text-tertiary transition-[color,background-color,transform] active:scale-[0.97]',
                                  secondaryGroups.some(({ entries }) =>
                                      entries.some(
                                          ({ href }) => href === activeHref,
                                      ),
                                  ) &&
                                      'bg-admin-accent/15 text-admin-accent-text',
                              )}
                          >
                              <Menu className="size-5" strokeWidth={1.8} />
                              Más
                          </button>
                      </nav>
                      <Sheet open={moreOpen} onOpenChange={setMoreOpen}>
                          <SheetContent
                              side="bottom"
                              overlayClassName="bg-overlay/70"
                              className="admin-shell max-h-[85vh] gap-0 overflow-y-auto rounded-t-3xl border-admin-border bg-admin-surface px-4 pt-4 pb-[max(1rem,env(safe-area-inset-bottom))] md:hidden"
                          >
                              <SheetTitle className="px-2 pb-2 text-lg text-admin-text-primary">
                                  Más opciones
                              </SheetTitle>
                              {secondaryGroups.map((group) => (
                                  <section
                                      key={group.label}
                                      aria-label={group.label}
                                  >
                                      <h2 className="px-2 pt-3 pb-2 text-xs font-semibold tracking-[0.12em] text-admin-text-tertiary uppercase">
                                          {group.label}
                                      </h2>
                                      <div className="grid grid-cols-2 gap-2">
                                          {group.entries.map(
                                              ({ label, href, icon }) => {
                                                  const Icon = icons[icon];
                                                  const active =
                                                      href === activeHref;

                                                  return (
                                                      <Link
                                                          key={href}
                                                          href={href}
                                                          aria-current={
                                                              active
                                                                  ? 'page'
                                                                  : undefined
                                                          }
                                                          onClick={() =>
                                                              setMoreOpen(false)
                                                          }
                                                          className={cn(
                                                              'flex min-h-14 items-center gap-3 rounded-xl bg-admin-surface-raised px-4 text-sm font-medium text-admin-text-secondary active:scale-[0.98]',
                                                              active &&
                                                                  'text-admin-accent-text ring-1 ring-admin-accent/30',
                                                          )}
                                                      >
                                                          <Icon
                                                              className="size-5"
                                                              strokeWidth={1.7}
                                                          />
                                                          {label}
                                                      </Link>
                                                  );
                                              },
                                          )}
                                      </div>
                                  </section>
                              ))}
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
            <aside className="admin-sidebar sticky top-0 hidden h-screen w-[6.5rem] flex-col items-start rounded-r-2xl bg-admin-surface md:col-start-1 md:row-start-1 md:flex 2xl:w-[14.5rem]">
                <Tooltip>
                    <TooltipTrigger asChild>
                        {/* First visible entry: the POS, or Pedidos for roles that cannot sell. */}
                        <Link
                            href={groups[0]?.entries[0]?.href ?? '/admin'}
                            aria-label={`${restaurant.name} POS`}
                            className="mt-6 ml-6 flex size-14 shrink-0 items-center justify-center overflow-hidden rounded-xl bg-admin-accent ring-1 ring-admin-border focus-visible:ring-2 focus-visible:ring-admin-accent focus-visible:outline-none"
                        >
                            <BrandLogo alt="" className="size-full" />
                        </Link>
                    </TooltipTrigger>
                    <TooltipContent side="right">
                        {restaurant.name} POS
                    </TooltipContent>
                </Tooltip>

                <nav
                    aria-label="Navegación de administración"
                    className="mt-4 ml-3 flex min-h-0 w-[5.75rem] flex-1 flex-col overflow-x-hidden overflow-y-auto 2xl:w-[13.75rem]"
                >
                    {groups.map((group, index) => (
                        <Fragment key={group.label}>
                            {index > 0 && (
                                <hr
                                    aria-hidden="true"
                                    className="mx-3 my-2 w-8 shrink-0 border-admin-border 2xl:hidden"
                                />
                            )}
                            <p className="hidden shrink-0 px-3 pt-4 pb-1 text-[0.6875rem] font-semibold tracking-[0.12em] text-admin-text-tertiary uppercase 2xl:block">
                                {group.label}
                            </p>
                            {group.entries.map(({ label, href, icon }) => {
                                const Icon = icons[icon];
                                const active = href === activeHref;

                                return (
                                    <div
                                        key={href}
                                        data-active={active}
                                        className="admin-nav-slot relative flex min-h-14 w-full shrink-0 items-center pl-3 2xl:min-h-12 2xl:pl-1.5"
                                    >
                                        <Tooltip>
                                            <TooltipTrigger asChild>
                                                <Link
                                                    href={href}
                                                    aria-label={label}
                                                    aria-current={
                                                        active
                                                            ? 'page'
                                                            : undefined
                                                    }
                                                    className={cn(
                                                        'relative flex size-14 items-center justify-center rounded-[0.5rem] text-admin-accent-text transition-colors focus-visible:ring-2 focus-visible:ring-admin-accent focus-visible:outline-none 2xl:h-11 2xl:w-[11.75rem] 2xl:justify-start 2xl:gap-3 2xl:px-3',
                                                        active &&
                                                            'bg-admin-accent text-admin-on-accent shadow-admin-nav-active',
                                                    )}
                                                >
                                                    <Icon
                                                        aria-hidden="true"
                                                        className="size-6 shrink-0 2xl:size-5"
                                                        strokeWidth={1.6}
                                                    />
                                                    <span className="hidden truncate text-sm font-medium 2xl:inline">
                                                        {label}
                                                    </span>
                                                </Link>
                                            </TooltipTrigger>
                                            <TooltipContent
                                                side="right"
                                                className="2xl:hidden"
                                            >
                                                {label}
                                            </TooltipContent>
                                        </Tooltip>
                                    </div>
                                );
                            })}
                        </Fragment>
                    ))}
                </nav>

                <Button
                    type="button"
                    variant="ghost"
                    aria-label="Cerrar sesión"
                    onClick={() => router.post('/logout')}
                    className="mt-auto mb-6 ml-6 size-14 shrink-0 rounded-[0.5rem] text-admin-accent-text hover:bg-transparent hover:text-admin-accent-text 2xl:ml-4.5 2xl:h-11 2xl:w-[11.75rem] 2xl:justify-start 2xl:gap-3 2xl:px-3"
                >
                    <LogOut className="size-6 2xl:size-5" strokeWidth={1.6} />
                    <span className="hidden text-sm font-medium 2xl:inline">
                        Cerrar sesión
                    </span>
                </Button>
            </aside>
            {mobileNavigation}
        </>
    );
}
