import { router, usePage } from '@inertiajs/react';
import { useState } from 'react';
import type { FormEvent } from 'react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import type { ManagementFilters } from '../types';

export function ManagementPeriodFilter({
    filters,
}: {
    filters: ManagementFilters;
}) {
    const { url } = usePage();
    const [period, setPeriod] = useState(filters.period);
    const [from, setFrom] = useState(filters.from);
    const [to, setTo] = useState(filters.to);
    function submit(event: FormEvent) {
        event.preventDefault();
        const query = new URL(url, 'http://localhost').searchParams;
        query.set('section', 'panorama');
        query.set('period', period);
        query.delete('page');
        query.delete('transaction_id');
        query.delete('from');
        query.delete('to');

        if (period === 'custom') {
            query.set('from', from);
            query.set('to', to);
        }

        router.get(`/admin/management?${query}`, {}, { preserveScroll: true });
    }

    return (
        <form
            aria-label="Período del panorama"
            onSubmit={submit}
            className="flex min-w-0 flex-wrap items-end gap-4"
        >
            <div className="grid w-full min-w-0 gap-2 sm:w-auto">
                <Label htmlFor="management-period">Período</Label>
                <select
                    id="management-period"
                    value={period}
                    onChange={(e) =>
                        setPeriod(e.target.value as ManagementFilters['period'])
                    }
                    className="h-11 w-full min-w-0 rounded-lg border border-admin-border bg-admin-surface px-3 text-admin-text-primary"
                >
                    <option value="today">Hoy</option>
                    <option value="7_days">Últimos 7 días</option>
                    <option value="30_days">Últimos 30 días</option>
                    <option value="this_month">Este mes</option>
                    <option value="previous_month">Mes anterior</option>
                    <option value="custom">Personalizado</option>
                </select>
            </div>
            {period === 'custom' && (
                <>
                    <div className="grid w-full min-w-0 gap-2 sm:w-auto">
                        <Label htmlFor="summary-from">Desde</Label>
                        <Input
                            id="summary-from"
                            type="date"
                            lang="es-AR"
                            className="[color-scheme:dark]"
                            value={from}
                            onChange={(e) => setFrom(e.target.value)}
                            required
                        />
                    </div>
                    <div className="grid w-full min-w-0 gap-2 sm:w-auto">
                        <Label htmlFor="summary-to">Hasta</Label>
                        <Input
                            id="summary-to"
                            type="date"
                            lang="es-AR"
                            className="[color-scheme:dark]"
                            value={to}
                            min={from}
                            onChange={(e) => setTo(e.target.value)}
                            required
                        />
                    </div>
                </>
            )}
            <Button type="submit" className="w-full sm:w-auto">
                Aplicar período
            </Button>
            <p className="w-full text-sm text-admin-text-secondary">
                {filters.from} — {filters.to} · Fecha del negocio
            </p>
        </form>
    );
}
