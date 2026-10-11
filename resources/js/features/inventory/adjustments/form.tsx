import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { FormField } from '@/features/inventory/components/form-field';
import { formatInventoryQuantity } from '@/features/inventory/presentation';
import {
    formatRecipeMoney as money,
    costCoverageLabels,
} from '@/features/inventory/recipes/presentation';
import type { InventoryUnit } from '@/features/inventory/types';
import { useForm } from '@inertiajs/react';
import { useRef, useState } from 'react';
import {
    adjustmentTypes,
    adjustmentWarnings,
    adjustmentCoverageLabels,
} from './presentation';
import type { AdjustmentItem, AdjustmentPreview } from './types';

export function AdjustmentForm({
    items,
    units,
    today,
}: {
    items: AdjustmentItem[];
    units: InventoryUnit[];
    today: string;
}) {
    const [key] = useState(() => crypto.randomUUID());
    const form = useForm({
        inventory_item_id: '',
        unit_id: '',
        type: 'count_correction',
        quantity: '',
        effective_date: today,
        idempotency_key: key,
        explicit_total_cost: '',
        note: '',
    });
    const [preview, setPreview] = useState<AdjustmentPreview | null>(null);
    const [previewErrors, setPreviewErrors] = useState<Record<string, string>>(
        {},
    );
    const [loading, setLoading] = useState(false);
    const revision = useRef(0);
    const item = items.find(
        (i) => String(i.id) === form.data.inventory_item_id,
    );
    const errors: Record<string, string> = { ...previewErrors, ...form.errors };
    const update = (v: Partial<typeof form.data>) => {
        revision.current++;
        setPreview(null);
        setPreviewErrors({});
        form.clearErrors();
        form.setData({ ...form.data, ...v });
    };
    const attrs = (field: string) => ({
        'aria-invalid': Boolean(errors[field]),
        'aria-describedby': errors[field]
            ? `adjustment-${field}-error`
            : undefined,
    });
    const isCount = form.data.type === 'count_correction';

    return (
        <form
            className="grid gap-4"
            aria-busy={loading || form.processing}
            onSubmit={async (e) => {
                e.preventDefault();
                const version = revision.current;
                setLoading(true);
                setPreview(null);
                setPreviewErrors({});

                try {
                    const token = decodeURIComponent(
                        document.cookie
                            .split('; ')
                            .find((c) => c.startsWith('XSRF-TOKEN='))
                            ?.slice('XSRF-TOKEN='.length) ?? '',
                    );
                    const response = await fetch(
                        '/admin/inventory/adjustments/preview',
                        {
                            method: 'POST',
                            credentials: 'same-origin',
                            headers: {
                                'Content-Type': 'application/json',
                                Accept: 'application/json',
                                'X-XSRF-TOKEN': token,
                            },
                            body: JSON.stringify({
                                ...form.data,
                                explicit_total_cost:
                                    form.data.explicit_total_cost || null,
                            }),
                        },
                    );

                    if (version !== revision.current) {
                        return;
                    }

                    const body = await response.json();

                    if (response.status === 422) {
                        const messages = body.errors as Record<
                            string,
                            string[]
                        >;
                        setPreviewErrors(
                            Object.fromEntries(
                                Object.entries(messages).map(([k, v]) => [
                                    k,
                                    v[0],
                                ]),
                            ),
                        );
                    } else if (response.ok) {
                        setPreview(body.data as AdjustmentPreview);
                    } else {
                        setPreviewErrors({
                            preview:
                                'No se pudo obtener la vista previa. Intentar nuevamente.',
                        });
                    }
                } catch {
                    if (version === revision.current) {
                        setPreviewErrors({
                            preview:
                                'No se pudo obtener la vista previa. Intentar nuevamente.',
                        });
                    }
                } finally {
                    setLoading(false);
                }
            }}
        >
            <p className="text-sm">
                Registrar una merma modifica inventario, no registra
                automáticamente un gasto financiero.
            </p>
            <FormField
                id="adjustment-inventory_item_id"
                label="Item"
                error={errors.inventory_item_id}
            >
                <select
                    {...attrs('inventory_item_id')}
                    id="adjustment-inventory_item_id"
                    className="min-h-11 w-full min-w-0 rounded border bg-admin-canvas p-2"
                    required
                    value={form.data.inventory_item_id}
                    onChange={(e) => {
                        const i = items.find(
                            (i) => String(i.id) === e.target.value,
                        );
                        update({
                            inventory_item_id: e.target.value,
                            unit_id: String(i?.default_input_unit_id ?? ''),
                        });
                    }}
                >
                    <option value="">Seleccionar item</option>
                    {items.map((i) => (
                        <option key={i.id} value={i.id}>
                            {i.name} · {i.category.name}
                        </option>
                    ))}
                </select>
            </FormField>
            <FormField
                id="adjustment-type"
                label="Tipo de ajuste"
                error={errors.type}
            >
                <select
                    {...attrs('type')}
                    id="adjustment-type"
                    className="min-h-11 w-full rounded border bg-admin-canvas p-2"
                    value={form.data.type}
                    onChange={(e) =>
                        update({
                            type: e.target.value,
                            quantity: '',
                            explicit_total_cost: '',
                        })
                    }
                >
                    {Object.entries(adjustmentTypes)
                        .filter(([k]) => k !== 'reversal')
                        .map(([k, v]) => (
                            <option key={k} value={k}>
                                {v}
                            </option>
                        ))}
                </select>
            </FormField>
            <div className="grid gap-4 sm:grid-cols-2">
                <FormField
                    id="adjustment-quantity"
                    label={
                        isCount
                            ? 'Conteo físico observado'
                            : form.data.type === 'manual_adjustment'
                              ? 'Cantidad de ajuste (con signo)'
                              : 'Cantidad perdida'
                    }
                    error={errors.quantity}
                    hint={
                        isCount
                            ? 'El delta se calcula al confirmar con el stock actual bloqueado.'
                            : form.data.type === 'manual_adjustment'
                              ? 'Positivo ingresa stock; negativo reduce stock.'
                              : 'Ingresar una cantidad positiva; se descontará del inventario.'
                    }
                >
                    <Input
                        {...attrs('quantity')}
                        aria-describedby={
                            errors.quantity
                                ? 'adjustment-quantity-error'
                                : 'adjustment-quantity-hint'
                        }
                        id="adjustment-quantity"
                        inputMode="decimal"
                        required
                        value={form.data.quantity}
                        onChange={(e) => update({ quantity: e.target.value })}
                    />
                </FormField>
                <FormField
                    id="adjustment-unit_id"
                    label="Unidad"
                    error={errors.unit_id}
                >
                    <select
                        {...attrs('unit_id')}
                        id="adjustment-unit_id"
                        className="min-h-11 w-full rounded border bg-admin-canvas p-2"
                        required
                        value={form.data.unit_id}
                        onChange={(e) => update({ unit_id: e.target.value })}
                    >
                        <option value="">Seleccionar unidad</option>
                        {units
                            .filter((u) => u.family === item?.unit_family)
                            .map((u) => (
                                <option key={u.id} value={u.id}>
                                    {u.code}
                                </option>
                            ))}
                    </select>
                </FormField>
            </div>
            {(isCount || form.data.type === 'manual_adjustment') && (
                <FormField
                    id="adjustment-explicit_total_cost"
                    label="Costo total explícito (pesos enteros)"
                    error={errors.explicit_total_cost}
                    hint="Opcional, sólo para aumentos. Si se omite se usa costo actual confiable; de lo contrario queda desconocido."
                >
                    <Input
                        {...attrs('explicit_total_cost')}
                        aria-describedby={
                            errors.explicit_total_cost
                                ? 'adjustment-explicit_total_cost-error'
                                : 'adjustment-explicit_total_cost-hint'
                        }
                        id="adjustment-explicit_total_cost"
                        inputMode="numeric"
                        value={form.data.explicit_total_cost}
                        onChange={(e) =>
                            update({ explicit_total_cost: e.target.value })
                        }
                    />
                </FormField>
            )}
            <FormField
                id="adjustment-effective_date"
                label="Fecha"
                error={errors.effective_date}
            >
                <Input
                    {...attrs('effective_date')}
                    id="adjustment-effective_date"
                    type="date"
                    required
                    value={form.data.effective_date}
                    onChange={(e) => update({ effective_date: e.target.value })}
                />
            </FormField>
            <FormField
                id="adjustment-note"
                label="Motivo / notas"
                error={errors.note}
            >
                <Input
                    {...attrs('note')}
                    id="adjustment-note"
                    maxLength={240}
                    value={form.data.note}
                    onChange={(e) => update({ note: e.target.value })}
                />
            </FormField>
            {errors.preview && <p role="alert">{errors.preview}</p>}
            {errors.idempotency_key && (
                <p role="alert">{errors.idempotency_key}</p>
            )}
            <Button type="submit" disabled={loading || form.processing}>
                {loading ? 'Calculando…' : 'Ver vista previa'}
            </Button>
            {preview && item && (
                <section
                    aria-label="Vista previa del ajuste"
                    className="grid gap-2 rounded border p-4"
                    aria-live="polite"
                >
                    <h2 className="font-semibold">Vista previa del ajuste</h2>
                    <p>
                        Stock actual:{' '}
                        {formatInventoryQuantity(
                            preview.current_stock,
                            item.base_unit,
                        )}
                    </p>
                    <p>
                        Delta:{' '}
                        {formatInventoryQuantity(preview.delta, item.base_unit)}
                    </p>
                    <p>
                        Stock resultante:{' '}
                        {formatInventoryQuantity(
                            preview.result_stock,
                            item.base_unit,
                        )}
                    </p>
                    <p>
                        Impacto económico estimado: {money(preview.cost_total)}{' '}
                        · {costCoverageLabels[preview.cost_coverage]}
                    </p>
                    <p>
                        Valoración actual:{' '}
                        {adjustmentCoverageLabels[preview.current_coverage]} ·{' '}
                        {money(preview.current_value)}
                    </p>
                    <p>
                        Valoración resultante:{' '}
                        {adjustmentCoverageLabels[preview.result_coverage]} ·{' '}
                        {money(preview.result_value)}
                    </p>
                    {preview.warnings.map((w) => (
                        <p key={w} role="status">
                            {adjustmentWarnings[w] ?? w}
                        </p>
                    ))}
                    <p className="text-sm">
                        La vista previa no es definitiva. Al confirmar se
                        recalculan stock y costo dentro de la transacción.
                    </p>
                    <Button
                        type="button"
                        disabled={!preview.can_confirm || form.processing}
                        onClick={() => {
                            form.transform((v) => ({
                                ...v,
                                explicit_total_cost:
                                    v.explicit_total_cost || null,
                            }));
                            form.post('/admin/inventory/adjustments');
                        }}
                    >
                        Confirmar ajuste
                    </Button>
                </section>
            )}
        </form>
    );
}
