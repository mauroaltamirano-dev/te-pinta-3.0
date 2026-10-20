import { Head, Link, usePoll } from '@inertiajs/react';
import { AdminPageShell } from '@/components/shared/admin-page-shell';
import { Badge } from '@/components/ui/badge';
import type { TableBoardRow } from '@/features/tables/types';
import { formatMoney } from '@/lib/format';
import { cn } from '@/lib/utils';

function status(table: TableBoardRow): { label: string; tone: string } {
    if (table.session?.bill_requested) {
        return {
            label: 'Pide la cuenta',
            tone: 'bg-warning-subtle text-warning',
        };
    }

    if (table.session) {
        return { label: 'Ocupada', tone: 'bg-info-subtle text-info' };
    }

    return table.available
        ? { label: 'Libre', tone: 'bg-success-subtle text-success' }
        : { label: 'Inactiva', tone: 'bg-muted text-muted-foreground' };
}

export default function TablesIndex({ tables }: { tables: TableBoardRow[] }) {
    // Bill requests and other waiters' tables show up without reloading the phone.
    usePoll(10_000, { only: ['tables'] });

    return (
        <>
            <Head title="Mesas" />
            <AdminPageShell
                title="Mesas"
                description="Tocá una mesa para cargar una ronda o ver su cuenta."
            >
                {tables.length === 0 ? (
                    <p className="rounded-xl border border-dashed border-admin-border p-6 text-center text-sm text-muted-foreground">
                        No hay mesas activas. Se configuran en el plano del
                        restaurante.
                    </p>
                ) : (
                    <ul className="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4 xl:grid-cols-6">
                        {tables.map((table) => {
                            const badge = status(table);

                            return (
                                <li key={table.id}>
                                    <Link
                                        href={`/admin/tables/${table.id}`}
                                        aria-label={`Mesa ${table.code}`}
                                        className={cn(
                                            'flex min-h-32 flex-col justify-between rounded-xl border border-admin-border bg-admin-surface p-4 transition-colors hover:border-admin-accent',
                                            table.session?.bill_requested &&
                                                'border-warning',
                                            !table.session &&
                                                !table.available &&
                                                'opacity-60',
                                        )}
                                    >
                                        <span className="flex items-start justify-between gap-2">
                                            <span>
                                                <span className="block text-2xl font-semibold">
                                                    {table.code}
                                                </span>
                                                <span className="text-xs text-muted-foreground">
                                                    {table.area}
                                                </span>
                                            </span>
                                            <Badge
                                                className={cn(
                                                    'border-transparent',
                                                    badge.tone,
                                                )}
                                            >
                                                {badge.label}
                                            </Badge>
                                        </span>
                                        {table.session && (
                                            <span className="mt-3 text-sm">
                                                <span className="block font-semibold tabular-nums">
                                                    {formatMoney(
                                                        table.session.total,
                                                    )}
                                                </span>
                                                <span className="text-muted-foreground">
                                                    {table.session.mine
                                                        ? 'Tu mesa'
                                                        : table.session.waiter}
                                                </span>
                                            </span>
                                        )}
                                    </Link>
                                </li>
                            );
                        })}
                    </ul>
                )}
            </AdminPageShell>
        </>
    );
}
