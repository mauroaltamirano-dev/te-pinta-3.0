import { usePage } from '@inertiajs/react';
import type { PropsWithChildren } from 'react';
import { AdminSidebar } from '@/components/shared/admin-sidebar';

export default function AdminLayout({ children }: PropsWithChildren) {
    const { component } = usePage();
    const usesOrdersDashboardComposition = component === 'admin/orders/index';

    return (
        <div className="dark admin-shell min-h-screen bg-background text-foreground md:grid md:grid-cols-[6.5rem_1fr]">
            <AdminSidebar />
            <div className="min-w-0">
                {!usesOrdersDashboardComposition && (
                    <header className="border-b border-border bg-transparent px-5 py-4 sm:px-6">
                        <p className="text-xs font-semibold tracking-[0.2em] text-muted-foreground uppercase">
                            Administración
                        </p>
                    </header>
                )}
                <main className="p-5 sm:p-6">{children}</main>
            </div>
        </div>
    );
}
