import { Head, Link, useForm } from '@inertiajs/react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
    DialogDescription,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import {
    adjustmentTypes,
    adjustmentCostLabels,
    adjustmentWarnings,
} from '@/features/inventory/adjustments/presentation';
import type { Adjustment } from '@/features/inventory/adjustments/types';
import { FormField } from '@/features/inventory/components/form-field';
import { formatInventoryQuantity } from '@/features/inventory/presentation';
import {
    costCoverageLabels,
    formatRecipeMoney as money,
} from '@/features/inventory/recipes/presentation';
export default function ShowAdjustment({
    adjustment: a,
    business_today,
}: {
    adjustment: Adjustment;
    business_today: string;
}) {
    const [open, setOpen] = useState(false);
    const form = useForm({ effective_date: business_today, note: '' });

    return (
        <section className="grid gap-5 text-admin-text-primary">
            <Head title={`Ajuste #${a.id}`} />
            <Link href="/admin/inventory/adjustments">Volver a Ajustes</Link>
            <h1 className="text-2xl font-semibold">
                {adjustmentTypes[a.type]} · {a.item.name}
            </h1>
            <article className="grid gap-2 rounded border p-4">
                <p>
                    Registrado por: {a.creator.name} ·{' '}
                    {new Date(a.created_at).toLocaleString('es-AR')}
                </p>
                <p>Fecha efectiva: {a.effective_date.slice(0, 10)}</p>
                <p>
                    Stock al registrar:{' '}
                    {formatInventoryQuantity(a.stock_before, a.item.base_unit)}
                </p>
                {a.physical_count !== null && (
                    <p>
                        Conteo físico observado:{' '}
                        {formatInventoryQuantity(
                            a.physical_count,
                            a.item.base_unit,
                        )}
                    </p>
                )}
                <p>
                    Movimiento:{' '}
                    {formatInventoryQuantity(a.delta, a.item.base_unit)}
                </p>
                <p>
                    Costo histórico: {money(a.cost_total)} ·{' '}
                    {costCoverageLabels[a.cost_coverage]}
                </p>
                <p>
                    Origen del costo: {adjustmentCostLabels[a.cost_status]} ·{' '}
                    {a.cost_method === 'moving_weighted_average'
                        ? 'Promedio ponderado'
                        : 'Sin base de costo'}
                </p>
                {a.cost_basis_quantity !== null && (
                    <p>
                        Base histórica:{' '}
                        {formatInventoryQuantity(
                            a.cost_basis_quantity,
                            a.item.base_unit,
                        )}{' '}
                        · {money(a.cost_basis_total)}
                    </p>
                )}
                {a.warnings.map((w) => (
                    <p key={w} role="status">
                        {adjustmentWarnings[w] ?? w}
                    </p>
                ))}
                {a.note && <p>Motivo / notas: {a.note}</p>}
                <p>
                    El costo registrado no cambia con los precios actuales. No
                    representa un gasto financiero ni COGS de venta.
                </p>
            </article>
            <Link
                className="underline"
                href={`/admin/inventory?transaction_id=${a.inventory_transaction_id}`}
            >
                Ver movimiento histórico en Inventario
            </Link>
            {a.reversal_of_id !== null ? (
                <Link href={`/admin/inventory/adjustments/${a.reversal_of_id}`}>
                    Ver ajuste original
                </Link>
            ) : a.reversal ? (
                <p role="status">
                    Revertido:{' '}
                    <Link
                        className="underline"
                        href={`/admin/inventory/adjustments/${a.reversal.id}`}
                    >
                        {a.reversal.note}
                    </Link>
                </p>
            ) : (
                <Button variant="outline" onClick={() => setOpen(true)}>
                    Revertir ajuste
                </Button>
            )}
            <Dialog open={open} onOpenChange={setOpen}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Revertir ajuste</DialogTitle>
                        <DialogDescription>
                            Se registra un movimiento opuesto exacto con el
                            costo histórico. No se borra ni se recalcula el
                            original.
                        </DialogDescription>
                    </DialogHeader>
                    <form
                        className="grid gap-4"
                        onSubmit={(e) => {
                            e.preventDefault();
                            form.post(
                                `/admin/inventory/adjustments/${a.id}/reverse`,
                                { onSuccess: () => setOpen(false) },
                            );
                        }}
                    >
                        <FormField
                            id="adjustment-reverse-date"
                            label="Fecha de reversión"
                            error={form.errors.effective_date}
                        >
                            <Input
                                id="adjustment-reverse-date"
                                type="date"
                                required
                                value={form.data.effective_date}
                                onChange={(e) =>
                                    form.setData(
                                        'effective_date',
                                        e.target.value,
                                    )
                                }
                                aria-invalid={Boolean(
                                    form.errors.effective_date,
                                )}
                                aria-describedby={
                                    form.errors.effective_date
                                        ? 'adjustment-reverse-date-error'
                                        : undefined
                                }
                            />
                        </FormField>
                        <FormField
                            id="adjustment-reverse-note"
                            label="Motivo de reversión"
                            error={form.errors.note}
                        >
                            <Input
                                id="adjustment-reverse-note"
                                required
                                maxLength={240}
                                value={form.data.note}
                                onChange={(e) =>
                                    form.setData('note', e.target.value)
                                }
                                aria-invalid={Boolean(form.errors.note)}
                                aria-describedby={
                                    form.errors.note
                                        ? 'adjustment-reverse-note-error'
                                        : undefined
                                }
                            />
                        </FormField>
                        {(form.errors as Record<string, string>).adjustment && (
                            <p role="alert">
                                {
                                    (form.errors as Record<string, string>)
                                        .adjustment
                                }
                            </p>
                        )}
                        <Button disabled={form.processing} type="submit">
                            Confirmar reversión
                        </Button>
                    </form>
                </DialogContent>
            </Dialog>
        </section>
    );
}
