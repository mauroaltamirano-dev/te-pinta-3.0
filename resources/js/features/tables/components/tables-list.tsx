import { Link } from '@inertiajs/react';
import { Badge } from '@/components/ui/badge';
import { tableState, tableStates } from '@/features/tables/table-status';
import type { TableBoardRow } from '@/features/tables/types';
import { formatMoney } from '@/lib/format';
import { cn } from '@/lib/utils';

export function TablesList({ tables }: { tables: TableBoardRow[] }) {
    return (
        <ul className="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4 xl:grid-cols-6">
            {tables.map((table) => {
                const badge = tableStates[tableState(table)];

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
                                        badge.badge,
                                    )}
                                >
                                    {badge.label}
                                </Badge>
                            </span>
                            {table.session && (
                                <span className="mt-3 text-sm">
                                    <span className="block font-semibold tabular-nums">
                                        {formatMoney(table.session.total)}
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
    );
}
