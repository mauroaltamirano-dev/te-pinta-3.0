import { Head } from '@inertiajs/react';
import { AdminPageShell } from '@/components/shared/admin-page-shell';
import { Button } from '@/components/ui/button';
import type { PrintJob } from '@/features/printing/types';
import { usePrintStation } from '@/features/printing/use-print-station';

const STATUS_LABELS: Record<PrintJob['status'], string> = {
    pending: 'En cola',
    claimed: 'Sin confirmar',
    printed: 'Impreso',
};

function jobLabel(job: PrintJob): string {
    const kind = job.kind === 'bill' ? 'Cuenta' : 'Comanda';
    const round =
        job.kind === 'kitchen_ticket' && job.payload.round
            ? ` · Ronda ${job.payload.round}`
            : '';

    return `${kind} · ${job.payload.title}${round}${job.reprint ? ' · reimpresión' : ''}`;
}

function time(iso: string | null): string {
    return iso
        ? new Date(iso).toLocaleTimeString('es-AR', {
              hour: '2-digit',
              minute: '2-digit',
          })
        : '—';
}

export default function PrintStation({ restaurant }: { restaurant: string }) {
    const station = usePrintStation(restaurant);
    const pending = station.status?.pending ?? 0;
    const unconfirmed = station.unconfirmed;

    return (
        <>
            <Head title="Estación de impresión" />
            <AdminPageShell
                title="Estación de impresión"
                description="Imprime las comandas y cuentas en la impresora de esta PC. Dejala abierta en su propia ventana, al frente."
                actions={
                    <>
                        <Button
                            variant="outline"
                            className="h-11"
                            onClick={() => void station.printTest()}
                        >
                            Imprimir prueba
                        </Button>
                        <Button
                            className="h-11"
                            variant={station.active ? 'outline' : 'default'}
                            onClick={() => station.setActive(!station.active)}
                        >
                            {station.active
                                ? 'Desactivar esta PC'
                                : 'Activar esta PC como estación'}
                        </Button>
                    </>
                }
            >
                <div className="grid gap-6">
                    {station.alerts.length > 0 && (
                        <div className="grid gap-3" aria-live="assertive">
                            {station.alerts.map((alert) => (
                                <div
                                    key={alert.id}
                                    role="alert"
                                    data-alert={alert.id}
                                    className="rounded-xl border-2 border-danger bg-danger-subtle p-4 text-danger"
                                >
                                    <p className="text-xl font-bold">
                                        {alert.title}
                                    </p>
                                    <p className="mt-1 text-base">
                                        {alert.detail}
                                    </p>
                                    {alert.action && (
                                        <Button asChild className="mt-3 h-11">
                                            <a href={alert.action.href}>
                                                {alert.action.label}
                                            </a>
                                        </Button>
                                    )}
                                </div>
                            ))}
                        </div>
                    )}

                    {station.wasHidden && station.active && (
                        <div
                            role="status"
                            className="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-warning bg-warning-subtle p-4 text-warning"
                        >
                            <p>
                                La ventana estuvo en segundo plano: Chrome frena
                                las páginas ocultas y las comandas pueden
                                demorarse. Dejala al frente.
                            </p>
                            <Button
                                variant="outline"
                                onClick={station.dismissHidden}
                            >
                                Entendido
                            </Button>
                        </div>
                    )}

                    <div className="grid gap-4 sm:grid-cols-3">
                        <div className="rounded-xl border border-admin-border bg-admin-surface p-5">
                            <p className="text-sm text-admin-text-secondary">
                                Pendientes
                            </p>
                            <p
                                className="text-6xl font-bold text-admin-text-primary tabular-nums"
                                data-testid="pending-count"
                            >
                                {station.status ? pending : '—'}
                            </p>
                        </div>
                        <div className="rounded-xl border border-admin-border bg-admin-surface p-5">
                            <p className="text-sm text-admin-text-secondary">
                                Sin confirmar
                            </p>
                            <p className="text-6xl font-bold text-admin-text-primary tabular-nums">
                                {station.status?.unconfirmed ?? '—'}
                            </p>
                        </div>
                        <div className="rounded-xl border border-admin-border bg-admin-surface p-5">
                            <p className="text-sm text-admin-text-secondary">
                                Esta PC
                            </p>
                            <p
                                className="mt-2 text-2xl font-semibold text-admin-text-primary"
                                data-testid="station-state"
                            >
                                {station.active
                                    ? 'Imprimiendo'
                                    : 'Sin imprimir'}
                            </p>
                            <p className="mt-1 text-sm text-admin-text-tertiary">
                                {station.connection === 'ok' &&
                                station.lastContactAt
                                    ? `Conectada · ${station.lastContactAt.toLocaleTimeString('es-AR')}`
                                    : 'Sin contacto con el servidor'}
                            </p>
                        </div>
                    </div>

                    {unconfirmed.length > 0 && (
                        <section className="grid gap-3">
                            <h2 className="text-lg font-semibold text-admin-text-primary">
                                Sin confirmar
                            </h2>
                            <p className="text-sm text-admin-text-secondary">
                                Se enviaron a la impresora y no se confirmaron.
                                Si salió el papel, confirmalo; si no, mandalo de
                                nuevo a la cola.
                            </p>
                            <ul className="grid gap-2">
                                {unconfirmed.map((job) => (
                                    <li
                                        key={job.id}
                                        className="flex flex-wrap items-center justify-between gap-3 rounded-lg border border-admin-border bg-admin-surface p-3"
                                    >
                                        <span className="text-admin-text-primary">
                                            {jobLabel(job)} ·{' '}
                                            {time(job.claimed_at)}
                                        </span>
                                        <span className="flex gap-2">
                                            <Button
                                                onClick={() =>
                                                    void station.confirmPrinted(
                                                        job.id,
                                                    )
                                                }
                                            >
                                                Salió
                                            </Button>
                                            <Button
                                                variant="outline"
                                                onClick={() =>
                                                    void station.release(job.id)
                                                }
                                            >
                                                No salió
                                            </Button>
                                        </span>
                                    </li>
                                ))}
                            </ul>
                        </section>
                    )}

                    <section className="grid gap-3">
                        <h2 className="text-lg font-semibold text-admin-text-primary">
                            Últimos trabajos
                        </h2>
                        {station.recent.length === 0 ? (
                            <p className="text-sm text-admin-text-tertiary">
                                Todavía no hay trabajos.
                            </p>
                        ) : (
                            <ul className="grid gap-2">
                                {station.recent.map((job) => (
                                    <li
                                        key={job.id}
                                        className="flex flex-wrap items-center justify-between gap-3 rounded-lg border border-admin-border bg-admin-surface p-3"
                                    >
                                        <span className="min-w-0 text-admin-text-primary">
                                            {jobLabel(job)}
                                            <span className="block text-sm text-admin-text-tertiary">
                                                {STATUS_LABELS[job.status]} ·{' '}
                                                {time(
                                                    job.printed_at ??
                                                        job.created_at,
                                                )}
                                            </span>
                                        </span>
                                        {job.status === 'printed' && (
                                            <Button
                                                variant="outline"
                                                onClick={() =>
                                                    void station.reprint(job.id)
                                                }
                                            >
                                                Reimprimir
                                            </Button>
                                        )}
                                    </li>
                                ))}
                            </ul>
                        )}
                    </section>
                </div>
            </AdminPageShell>
        </>
    );
}
