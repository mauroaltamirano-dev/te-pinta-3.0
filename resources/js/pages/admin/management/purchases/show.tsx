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
    formatDecimalQuantity,
    formatInventoryQuantity,
} from '@/features/inventory/presentation';
import { AttachmentSection } from '@/features/management/components/attachment-section';
import type { Purchase } from '@/features/purchases/types';
import { formatMoney as money } from '@/lib/format';
export default function ShowPurchase({
    purchase: p,
    business_today,
}: {
    purchase: Purchase;
    business_today: string;
}) {
    const [reversing, setReversing] = useState(false);
    const form = useForm({ effective_date: business_today, description: '' });

    return (
        <section className="grid gap-6 text-admin-text-primary">
            <Head title={`Compra #${p.id}`} />
            <Link href="/admin/management/purchases">Volver a Compras</Link>
            <header>
                <h1 className="text-2xl font-semibold text-balance">
                    Compra #{p.id}
                </h1>
                <p>
                    {p.effective_date} · {p.supplier_name ?? 'Sin proveedor'} ·{' '}
                    {p.wallet.name}
                </p>
                <p>{p.description}</p>
                <p>{p.status === 'reversed' ? 'Revertida' : 'Confirmada'}</p>
            </header>
            <section className="rounded-xl border border-admin-border bg-admin-surface p-4">
                <h2>Impacto registrado</h2>
                <p className="tabular-nums">
                    Insumos: {money(p.subtotal)} · Envío:{' '}
                    {money(p.shipping_amount)} · Egreso total: {money(p.total)}
                </p>
                <p>
                    Importes y cantidades históricos; no se recalculan con el
                    catálogo actual.
                </p>
                {p.inventory_transaction_id ? (
                    <Link
                        href={`/admin/inventory?transaction_id=${p.inventory_transaction_id}`}
                    >
                        Ver entrada física original
                    </Link>
                ) : (
                    <p>Esta compra no generó stock físico.</p>
                )}
            </section>
            <ul className="grid gap-3">
                {p.lines.map((l) => (
                    <li
                        key={l.id}
                        className="rounded-xl border border-admin-border bg-admin-surface p-4"
                    >
                        <h2>{l.item_name}</h2>
                        <p>
                            {l.category_name} ·{' '}
                            {l.presentation_name
                                ? `${formatDecimalQuantity(l.quantity)} × ${l.presentation_name}`
                                : formatInventoryQuantity(
                                      l.quantity,
                                      l.input_unit_code ?? l.base_unit_code,
                                  )}
                        </p>
                        <p>
                            {formatInventoryQuantity(
                                l.base_quantity,
                                l.base_unit_code,
                            )}{' '}
                            ·{' '}
                            {l.tracks_stock
                                ? 'Entrada física registrada'
                                : 'Sin impacto en stock'}
                        </p>
                        <p className="tabular-nums">{money(l.line_total)}</p>
                    </li>
                ))}
            </ul>
            <AttachmentSection
                transactionId={p.financial_transaction.id}
                attachmentsCount={
                    p.financial_transaction.attachments_count ?? 0
                }
            />
            {p.reversal ? (
                <p>
                    Reversión del {p.reversal.effective_date}:{' '}
                    {p.reversal.description}. El original permanece en el
                    historial.
                </p>
            ) : (
                <Button
                    variant="destructive"
                    onClick={() => setReversing(true)}
                >
                    Revertir compra
                </Button>
            )}
            <Dialog open={reversing} onOpenChange={setReversing}>
                <DialogContent role="alertdialog">
                    <DialogHeader>
                        <DialogTitle>Revertir compra</DialogTitle>
                        <DialogDescription>
                            Se compensarán dinero, stock y costo juntos. El
                            original no se elimina. Confirmá la fecha y el
                            motivo.
                        </DialogDescription>
                    </DialogHeader>
                    <form
                        className="grid gap-4"
                        onSubmit={(e) => {
                            e.preventDefault();
                            form.post(
                                `/admin/management/purchases/${p.id}/reverse`,
                                { onSuccess: () => setReversing(false) },
                            );
                        }}
                    >
                        <FormField
                            id="reversal-date"
                            label="Fecha de reversión"
                            error={form.errors.effective_date}
                        >
                            <Input
                                id="reversal-date"
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
                            id="reversal-reason"
                            label="Motivo"
                            error={form.errors.description}
                        >
                            <Input
                                id="reversal-reason"
                                required
                                maxLength={240}
                                value={form.data.description}
                                onChange={(e) =>
                                    form.setData('description', e.target.value)
                                }
                            />
                        </FormField>
                        {Object.values(form.errors).map((error) => (
                            <p role="alert" key={error}>
                                {error}
                            </p>
                        ))}
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => setReversing(false)}
                        >
                            Cancelar
                        </Button>
                        <Button
                            variant="destructive"
                            disabled={form.processing}
                        >
                            Confirmar reversión
                        </Button>
                    </form>
                </DialogContent>
            </Dialog>
        </section>
    );
}
