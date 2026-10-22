import { Head, Link, useForm } from '@inertiajs/react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { FormField } from '@/features/inventory/components/form-field';
import {
    formatInventoryQuantity,
    inventoryUnitLabel,
} from '@/features/inventory/presentation';
import {
    costCoverageLabels,
    productionWarningLabels,
} from '@/features/inventory/recipes/presentation';
import type { ProductionBatch } from '@/features/inventory/recipes/types';
import { formatCost as money } from '@/lib/format';
export default function ShowProduction({
    batch: b,
    business_today,
}: {
    batch: ProductionBatch;
    business_today: string;
}) {
    const [open, setOpen] = useState(false);
    const form = useForm({ effective_date: business_today, reason: '' });

    return (
        <section className="grid gap-6 text-admin-text-primary">
            <Head title={`Producción #${b.id}`} />
            <Link href="/admin/inventory/production">Volver a Producción</Link>
            <h1 className="text-2xl font-semibold text-balance">
                {b.recipe_version.recipe_name_snapshot} · Lote #{b.id}
            </h1>
            <Link
                className="underline"
                href={`/admin/inventory/recipes/${b.recipe_version.recipe_id}${b.recipe_version.is_active ? '' : '?view=retired'}`}
            >
                Ver fórmula · Versión {b.recipe_version.version}
            </Link>
            <article className="grid gap-3 rounded-xl border border-admin-border bg-admin-surface p-4">
                <h2 className="text-xl font-semibold">Resultado histórico</h2>
                <p>
                    Esperado:{' '}
                    {formatInventoryQuantity(
                        b.expected_output_base_quantity,
                        b.output_item.base_unit,
                    )}
                </p>
                <p>
                    Producido:{' '}
                    {formatInventoryQuantity(
                        b.actual_output_quantity,
                        b.output_unit,
                    )}
                </p>
                <p>Costo histórico del lote: {money(b.total_cost)}</p>
                <p>
                    Costo histórico por unidad real:{' '}
                    {money(b.historical_cost_per_output_unit)} /{' '}
                    {inventoryUnitLabel('1', b.output_unit)}
                </p>
                <p>{costCoverageLabels[b.cost_coverage]}</p>
                <p className="text-sm text-admin-text-secondary">
                    Este costo no cambia con los precios actuales. Se atribuye a
                    la salida real, no a la esperada.
                </p>
                {b.operational_warnings.length > 0 && (
                    <ul role="status">
                        {b.operational_warnings.map((w, n) => (
                            <li key={n}>
                                {productionWarningLabels[w.code] ?? w.code}
                            </li>
                        ))}
                    </ul>
                )}
            </article>
            <section className="grid gap-3">
                <h2 className="text-xl font-semibold">
                    Movimientos y costo transferido
                </h2>
                <ul className="grid gap-2">
                    {b.transaction.movements.map((m) => (
                        <li key={m.id}>
                            {m.item.name}:{' '}
                            {formatInventoryQuantity(
                                m.quantity,
                                m.item.base_unit,
                            )}{' '}
                            · {money(m.cost_layer?.total_cost ?? null)}
                        </li>
                    ))}
                </ul>
                <Link
                    className="underline"
                    href={`/admin/inventory?transaction_id=${b.inventory_transaction_id}`}
                >
                    Ver movimientos originales en Inventario
                </Link>
            </section>
            {b.reversal ? (
                <p role="status">
                    Revertido: {b.reversal.reason} ·{' '}
                    {b.reversal.effective_date.slice(0, 10)}
                </p>
            ) : (
                <Button variant="outline" onClick={() => setOpen(true)}>
                    Revertir producción
                </Button>
            )}
            <Dialog open={open} onOpenChange={setOpen}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Revertir producción</DialogTitle>
                        <DialogDescription>
                            Se crearán movimientos opuestos exactos con los
                            costos originales. No se borra ni se recalcula el
                            lote.
                        </DialogDescription>
                    </DialogHeader>
                    <form
                        className="grid gap-4"
                        onSubmit={(e) => {
                            e.preventDefault();
                            form.post(
                                `/admin/inventory/production/${b.id}/reverse`,
                                { onSuccess: () => setOpen(false) },
                            );
                        }}
                    >
                        <FormField id="reverse-date" label="Fecha de reversión">
                            <Input
                                id="reverse-date"
                                type="date"
                                required
                                value={form.data.effective_date}
                                onChange={(e) =>
                                    form.setData(
                                        'effective_date',
                                        e.target.value,
                                    )
                                }
                            />
                        </FormField>
                        <FormField
                            id="reverse-reason"
                            label="Motivo"
                            error={form.errors.reason}
                        >
                            <Input
                                id="reverse-reason"
                                required
                                maxLength={240}
                                value={form.data.reason}
                                onChange={(e) =>
                                    form.setData('reason', e.target.value)
                                }
                            />
                        </FormField>
                        {Object.entries(form.errors).map(([k, v]) => (
                            <p key={k} role="alert">
                                {v}
                            </p>
                        ))}
                        <Button type="submit" disabled={form.processing}>
                            Confirmar reversión
                        </Button>
                    </form>
                </DialogContent>
            </Dialog>
        </section>
    );
}
