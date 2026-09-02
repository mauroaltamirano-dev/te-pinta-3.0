import { Head, Link } from '@inertiajs/react';
import { ArrowLeft, ShieldCheck } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { AuditDashboard } from '@/features/management/components/audit-dashboard';
import type { ManagementAuditPageProps } from '@/features/management/types';

export default function ManagementAudit({ audit }: ManagementAuditPageProps) {
    return (
        <>
            <Head title="Auditoría financiera" />
            <section className="grid gap-6 font-orders">
                <header className="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                    <div>
                        <p className="flex items-center gap-2 text-sm font-medium text-admin-accent">
                            <ShieldCheck
                                className="size-4"
                                aria-hidden="true"
                            />
                            Reconciliación financiera
                        </p>
                        <h1 className="mt-1 text-3xl font-semibold tracking-tight text-white">
                            Auditoría
                        </h1>
                        <p className="mt-1 max-w-2xl text-sm text-admin-text-tertiary">
                            Compará el ledger con sus entidades semánticas e
                            identificá situaciones que requieren atención. Esta
                            vista es de sólo lectura.
                        </p>
                    </div>
                    <Button
                        asChild
                        variant="outline"
                        className="h-11 rounded-lg px-4"
                    >
                        <Link href="/admin/management">
                            <ArrowLeft /> Volver a Gestión
                        </Link>
                    </Button>
                </header>

                <AuditDashboard audit={audit} />
            </section>
        </>
    );
}
