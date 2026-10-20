import { Head } from '@inertiajs/react';
import { ShieldCheck } from 'lucide-react';
import { AuditDashboard } from '@/features/management/components/audit-dashboard';
import { ManagementSectionNavigation } from '@/features/management/components/management-section-navigation';
import type { ManagementAuditPageProps } from '@/features/management/types';

export default function ManagementAudit({ audit }: ManagementAuditPageProps) {
    return (
        <>
            <Head title="Auditoría financiera" />
            <section className="grid gap-6 font-orders">
                <header className="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                    <div>
                        <p className="flex items-center gap-2 text-sm font-medium text-admin-accent-text">
                            <ShieldCheck
                                className="size-4"
                                aria-hidden="true"
                            />
                            Reconciliación financiera
                        </p>
                        <h1 className="mt-1 text-3xl font-semibold tracking-tight text-admin-text-primary">
                            Auditoría
                        </h1>
                        <p className="mt-1 max-w-2xl text-sm text-admin-text-tertiary">
                            Compará el ledger con sus entidades semánticas e
                            identificá situaciones que requieren atención. Esta
                            vista es de sólo lectura.
                        </p>
                    </div>
                </header>
                <ManagementSectionNavigation />

                <AuditDashboard audit={audit} />
            </section>
        </>
    );
}
