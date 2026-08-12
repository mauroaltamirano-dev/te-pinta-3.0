import type { PropsWithChildren } from 'react';
import { AdminSidebar } from '@/components/shared/admin-sidebar';

export default function AdminLayout({ children }: PropsWithChildren) {
    return (
        <div className="dark admin-shell min-h-screen bg-background text-foreground md:grid md:grid-cols-[6.5rem_1fr]">
            <AdminSidebar />
            <div className="min-w-0">
                <header className="border-b border-border bg-transparent px-5 py-4 sm:px-8">
                    <p className="text-xs font-semibold tracking-[0.2em] text-muted-foreground uppercase">
                        Administration
                    </p>
                </header>
                <main className="p-5 sm:p-8">{children}</main>
            </div>
        </div>
    );
}
