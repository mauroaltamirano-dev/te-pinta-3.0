import { Head, usePage, usePoll } from '@inertiajs/react';
import { AdminPageShell } from '@/components/shared/admin-page-shell';
import { RoundComposer } from '@/features/tables/components/round-composer';
import { TableSessionActions } from '@/features/tables/components/table-session-actions';
import type { TableDetail } from '@/features/tables/types';
import { formatMoney } from '@/lib/format';

export default function TableShow(detail: TableDetail) {
    const { table, session } = detail;
    const errors = Object.values(usePage().props.errors ?? {});
    // Rounds from caja and a charge made elsewhere reach this phone without a reload.
    usePoll(10_000, { only: ['session', 'missing_consumption'] });
    const canCompose = session ? session.can_act : table.available;

    return (
        <>
            <Head title={`Mesa ${table.code}`} />
            <AdminPageShell
                title={`Mesa ${table.code}`}
                description={
                    session
                        ? `${table.area} · ${session.mine ? 'Tu mesa' : `Mozo: ${session.waiter}`} · abierta ${session.opened_at}`
                        : `${table.area} · Libre`
                }
                breadcrumbs={[
                    { title: 'Mesas', href: '/admin/tables' },
                    { title: table.code, href: `/admin/tables/${table.id}` },
                ]}
            >
                <div className="mx-auto grid max-w-3xl gap-4">
                    {errors.length > 0 && (
                        <div
                            role="alert"
                            className="rounded-xl bg-danger-subtle p-4 text-sm text-danger"
                        >
                            {errors.map((error) => (
                                <p key={error}>{error}</p>
                            ))}
                        </div>
                    )}
                    {!session && !table.available && (
                        <p className="rounded-xl bg-warning-subtle p-4 text-sm text-warning">
                            La mesa está inactiva en el plano: no se puede
                            abrir.
                        </p>
                    )}
                    {session && !session.can_act && (
                        <p className="rounded-xl bg-warning-subtle p-4 text-sm text-warning">
                            Esta mesa la atiende {session.waiter}. Solo esa
                            persona o caja pueden cargarle rondas.
                        </p>
                    )}
                    {session?.bill_requested && (
                        <p className="rounded-xl bg-warning-subtle p-4 text-sm font-medium text-warning">
                            La mesa pidió la cuenta.
                        </p>
                    )}

                    {session && (
                        <section
                            aria-label="Total provisorio"
                            className="rounded-xl border border-admin-border bg-admin-surface p-4"
                        >
                            <p className="text-sm text-muted-foreground">
                                Total provisorio
                            </p>
                            <p
                                className="text-3xl font-semibold tabular-nums"
                                data-testid="table-total"
                            >
                                {formatMoney(session.total)}
                            </p>
                            <ul className="mt-2 text-sm">
                                {session.lines.map((line) => (
                                    <li
                                        key={`${line.product_id}-${line.addon_id}`}
                                    >
                                        <span className="font-semibold tabular-nums">
                                            {line.quantity} ×
                                        </span>{' '}
                                        {line.name}
                                    </li>
                                ))}
                            </ul>
                        </section>
                    )}

                    {session && (session.can_act || detail.can_cashier) && (
                        <TableSessionActions
                            detail={detail}
                            session={session}
                        />
                    )}

                    {canCompose && <RoundComposer detail={detail} />}

                    {session && session.rounds.length > 0 && (
                        <section aria-label="Rondas" className="grid gap-2">
                            <h2 className="text-lg font-semibold">Rondas</h2>
                            {[...session.rounds].reverse().map((round) => (
                                <article
                                    key={round.id}
                                    className="rounded-xl border border-admin-border bg-admin-surface p-3"
                                >
                                    <header className="flex justify-between gap-2 text-sm">
                                        <span className="font-semibold">
                                            {round.kind === 'void'
                                                ? `Anulación · ronda ${round.sequence}`
                                                : `Ronda ${round.sequence}`}
                                        </span>
                                        <span className="text-muted-foreground tabular-nums">
                                            {round.time}
                                        </span>
                                    </header>
                                    {round.added_by && (
                                        <p className="text-xs text-muted-foreground">
                                            Agregado por {round.added_by}
                                        </p>
                                    )}
                                    <ul className="mt-1">
                                        {round.items.map((item, index) => (
                                            <li key={index}>
                                                <span className="font-semibold tabular-nums">
                                                    {round.kind === 'void'
                                                        ? '−'
                                                        : ''}
                                                    {item.quantity} ×
                                                </span>{' '}
                                                {item.name}
                                            </li>
                                        ))}
                                    </ul>
                                    {round.note && (
                                        <p className="mt-1 text-sm">
                                            Nota: {round.note}
                                        </p>
                                    )}
                                </article>
                            ))}
                        </section>
                    )}
                </div>
            </AdminPageShell>
        </>
    );
}
