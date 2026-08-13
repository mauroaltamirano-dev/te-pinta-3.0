import { usePage } from '@inertiajs/react';
import type { PropsWithChildren } from 'react';
import { AdminSidebar } from '@/components/shared/admin-sidebar';
import { cn } from '@/lib/utils';

export default function AdminLayout({ children }: PropsWithChildren) {
    const { component } = usePage();
    const isQuickOrderHome = component === 'admin/home';
    const usesOrdersDashboardComposition = component === 'admin/orders/index';

    return (
        <div className="dark admin-shell min-h-screen bg-background text-foreground md:grid md:grid-cols-[6.5rem_1fr]">
            <AdminSidebar />
            <div className="min-w-0">
                {!usesOrdersDashboardComposition && !isQuickOrderHome && (
                    <header className="border-b border-border bg-transparent px-5 py-4 sm:px-6">
                        <p className="text-xs font-semibold tracking-[0.2em] text-muted-foreground uppercase">
                            Administración
                        </p>
                    </header>
                )}
                <main
                    className={cn(
                        isQuickOrderHome ? 'p-0' : 'p-5 sm:p-6',
                        isQuickOrderHome && 'lg:h-screen lg:overflow-hidden',
                    )}
                >
                    {children}
                </main>
            </div>
        </div>
    );
}
