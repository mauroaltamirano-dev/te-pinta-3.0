import { usePage } from '@inertiajs/react';
import type { PropsWithChildren } from 'react';
import { AdminSidebar } from '@/components/shared/admin-sidebar';
import { useRestaurantBranding } from '@/hooks/use-restaurant-branding';
import { cn } from '@/lib/utils';

export default function AdminLayout({ children }: PropsWithChildren) {
    useRestaurantBranding();
    const { component } = usePage();
    const isQuickOrderHome = component === 'admin/home';
    const usesDashboardComposition = component === 'admin/dashboard';
    const usesOrdersDashboardComposition = component === 'admin/orders/index';
    const usesSettingsWorkspace =
        component === 'admin/products/index' ||
        component === 'admin/settings/general';

    return (
        <div className="admin-shell relative isolate z-0 min-h-screen bg-background pb-24 text-foreground md:grid md:grid-cols-[6.5rem_1fr] md:pb-0 lg:grid-cols-[14.5rem_1fr]">
            <div
                className={cn(
                    'min-w-0 md:col-start-2 md:row-start-1',
                    usesSettingsWorkspace &&
                        'lg:flex lg:h-screen lg:min-h-0 lg:flex-col lg:overflow-hidden',
                    usesOrdersDashboardComposition &&
                        'xl:flex xl:h-screen xl:min-h-0 xl:flex-col xl:overflow-hidden',
                )}
            >
                {!usesDashboardComposition &&
                    !usesOrdersDashboardComposition &&
                    !isQuickOrderHome && (
                        <header className="shrink-0 border-b border-border bg-transparent px-5 py-4 sm:px-6">
                            <p className="text-xs font-semibold tracking-[0.2em] text-muted-foreground uppercase">
                                Administración
                            </p>
                        </header>
                    )}
                <main
                    className={cn(
                        isQuickOrderHome ? 'p-0' : 'p-5 sm:p-6',
                        isQuickOrderHome && 'lg:h-screen lg:overflow-hidden',
                        usesSettingsWorkspace &&
                            'lg:min-h-0 lg:flex-1 lg:overflow-hidden',
                        usesOrdersDashboardComposition &&
                            'xl:min-h-0 xl:flex-1 xl:overflow-hidden',
                    )}
                >
                    {children}
                </main>
            </div>
            <AdminSidebar />
        </div>
    );
}
