import { Head, usePoll } from '@inertiajs/react';
import { LayoutGrid, Map as MapIcon } from 'lucide-react';
import { useState } from 'react';
import { AdminPageShell } from '@/components/shared/admin-page-shell';
import { Button } from '@/components/ui/button';
import { TablesFloorPlan } from '@/features/tables/components/tables-floor-plan';
import { TablesList } from '@/features/tables/components/tables-list';
import type { TableBoardRow, TableFloorArea } from '@/features/tables/types';

const views = [
    { value: 'plan', label: 'Plano', icon: MapIcon },
    { value: 'list', label: 'Lista', icon: LayoutGrid },
] as const;

export default function TablesIndex({
    tables,
    areas,
}: {
    tables: TableBoardRow[];
    areas: TableFloorArea[];
}) {
    // Bill requests and other waiters' tables show up without reloading the phone.
    usePoll(10_000, { only: ['tables', 'areas'] });
    // The plan is how the waiter finds a table; the list stays one tap away.
    const [view, setView] = useState<'plan' | 'list'>('plan');

    return (
        <>
            <Head title="Mesas" />
            <AdminPageShell
                title="Mesas"
                description="Tocá una mesa para cargar una ronda o ver su cuenta."
                actions={
                    tables.length > 0 && (
                        <div
                            role="group"
                            aria-label="Vista de mesas"
                            className="flex gap-2"
                        >
                            {views.map(({ value, label, icon: Icon }) => (
                                <Button
                                    key={value}
                                    type="button"
                                    variant={
                                        view === value ? 'default' : 'outline'
                                    }
                                    aria-pressed={view === value}
                                    onClick={() => setView(value)}
                                    className="min-h-11"
                                >
                                    <Icon aria-hidden />
                                    {label}
                                </Button>
                            ))}
                        </div>
                    )
                }
            >
                {tables.length === 0 ? (
                    <p className="rounded-xl border border-dashed border-admin-border p-6 text-center text-sm text-muted-foreground">
                        No hay mesas activas. Se configuran en el plano del
                        restaurante.
                    </p>
                ) : view === 'plan' ? (
                    <TablesFloorPlan areas={areas} tables={tables} />
                ) : (
                    <TablesList tables={tables} />
                )}
            </AdminPageShell>
        </>
    );
}
