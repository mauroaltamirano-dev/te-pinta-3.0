import { Head, Link, useForm, usePage } from '@inertiajs/react';
import { useState } from 'react';
import { AdminPageShell } from '@/components/shared/admin-page-shell';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    basisPointsToPercent,
    percentage,
    percentToBasisPoints,
} from '@/features/inventory/costing/types';
import type {
    PackagingComponent,
    Policy,
    PolicyPayload,
    PolicyPresentation,
} from '@/features/inventory/costing/types';
import {
    editableDecimal,
    formatInventoryQuantity,
    inventoryUnitName,
} from '@/features/inventory/presentation';

type Item = {
    id: number;
    name: string;
    unit_family: string;
    default_input_unit_id: number;
};
type Unit = { id: number; code: string; name: string; family: string };
const selectClass =
    'w-full rounded-md border border-admin-border bg-admin-surface p-2';
/** Every sale the costing screen estimates for empanadas; unit products use 1. */
const standardQuantities = [1, 6, 12];

/** "la docena" / "3 unidades": how a sale is named inside a question. */
function saleName(quantity: number) {
    return (
        { 1: 'la unidad', 6: 'la media docena', 12: 'la docena' }[quantity] ??
        `${quantity} unidades`
    );
}
function question(quantity: number) {
    return standardQuantities.includes(quantity)
        ? `¿Qué caja o envoltorio lleva ${saleName(quantity)}?`
        : `¿Qué caja o envoltorio llevan ${saleName(quantity)}?`;
}
function capitalized(text: string) {
    return text.charAt(0).toUpperCase() + text.slice(1);
}

function payloadOf(policy: Policy | null): PolicyPayload {
    return {
        name: policy?.name ?? '',
        overhead_basis_points: policy?.overhead_basis_points ?? 0,
        is_active: policy?.is_active ?? true,
        presentations: (policy?.presentations ?? []).map((p) => ({
            commercial_quantity: p.commercial_quantity,
            packaging_mode: p.packaging_mode,
            components: p.components.map((c) => ({
                inventory_item_id: c.inventory_item_id,
                quantity: editableDecimal(c.quantity),
                unit_id: c.unit_id,
            })),
        })),
    };
}

function PolicySummary({
    policy,
    onEdit,
}: {
    policy: Policy;
    onEdit: () => void;
}) {
    const presentations = [...policy.presentations].sort(
        (a, b) => a.commercial_quantity - b.commercial_quantity,
    );
    const pending = standardQuantities.filter(
        (q) => !presentations.some((p) => p.commercial_quantity === q),
    );

    return (
        <Card className="admin-ui admin-panel" data-testid="policy-summary">
            <CardContent className="grid gap-2">
                <h2 className="font-semibold">
                    {policy.name}
                    {!policy.is_active && ' · Desactivado'}
                </h2>
                <p>
                    Gastos generales: {percentage(policy.overhead_basis_points)}
                </p>
                <ul className="grid gap-1 break-words">
                    {presentations.map((p) => (
                        <li key={p.commercial_quantity}>
                            {capitalized(saleName(p.commercial_quantity))}:{' '}
                            {p.packaging_mode === 'none'
                                ? 'no lleva caja ni envoltorio'
                                : p.components
                                      .map(
                                          (c) =>
                                              `${formatInventoryQuantity(c.quantity, c.unit?.code ?? '')} de ${c.item?.name}`,
                                      )
                                      .join(', ')}
                        </li>
                    ))}
                    {pending.length > 0 && (
                        <li className="text-admin-text-secondary">
                            Sin definir: {pending.map(saleName).join(', ')}
                        </li>
                    )}
                </ul>
                <Button
                    type="button"
                    variant="outline"
                    className="justify-self-start"
                    onClick={onEdit}
                >
                    Editar {policy.name}
                </Button>
            </CardContent>
        </Card>
    );
}

function PackagingQuestion({
    quantity,
    presentation,
    items,
    units,
    onChange,
    onRemove,
}: {
    quantity: number;
    presentation: PolicyPresentation | undefined;
    items: Item[];
    units: Unit[];
    onChange: (presentation: PolicyPresentation | undefined) => void;
    onRemove?: () => void;
}) {
    const sale = saleName(quantity);
    const answer = presentation?.packaging_mode ?? 'unknown';
    const components = presentation?.components ?? [];
    const emptyComponent: PackagingComponent = {
        inventory_item_id: 0,
        quantity: '1',
        unit_id: 0,
    };
    function choose(value: string) {
        if (value === 'unknown') {
            onChange(undefined);
        } else {
            onChange({
                commercial_quantity: quantity,
                packaging_mode: value as PolicyPresentation['packaging_mode'],
                components: value === 'components' ? [emptyComponent] : [],
            });
        }
    }
    function setComponents(next: PackagingComponent[]) {
        onChange({
            commercial_quantity: quantity,
            packaging_mode: 'components',
            components: next,
        });
    }
    const options: [string, string][] = [
        ['none', 'No lleva'],
        ['components', 'Lleva caja o envoltorio'],
        ...(onRemove
            ? []
            : [['unknown', 'Todavía no lo sé'] as [string, string]]),
    ];

    return (
        <fieldset
            className="grid min-w-0 gap-3 rounded-md border border-admin-border p-3"
            data-testid={`packaging-question-${quantity}`}
        >
            <legend className="px-1 font-medium">{question(quantity)}</legend>
            <div className="flex flex-wrap gap-x-4 gap-y-2">
                {options.map(([value, label]) => (
                    <label key={value} className="flex items-center gap-2">
                        <input
                            type="radio"
                            name={`packaging-${quantity}`}
                            value={value}
                            checked={answer === value}
                            onChange={() => choose(value)}
                        />
                        {label}
                    </label>
                ))}
            </div>
            {components.map((c, index) => {
                const n = index + 1;
                const item = items.find((i) => i.id === c.inventory_item_id);
                const change = (values: Partial<PackagingComponent>) =>
                    setComponents(
                        components.map((row, k) =>
                            k === index ? { ...row, ...values } : row,
                        ),
                    );

                return (
                    <div
                        key={index}
                        className="grid gap-2 sm:grid-cols-[2fr_1fr_1fr_auto] sm:items-end"
                    >
                        <div>
                            <Label htmlFor={`item-${quantity}-${n}`}>
                                Caja o envoltorio
                            </Label>
                            <select
                                id={`item-${quantity}-${n}`}
                                aria-label={`Caja o envoltorio ${n} para ${sale}`}
                                className={selectClass}
                                value={c.inventory_item_id || ''}
                                onChange={(e) => {
                                    const selected = items.find(
                                        (i) => i.id === Number(e.target.value),
                                    );
                                    change({
                                        inventory_item_id: selected?.id ?? 0,
                                        unit_id:
                                            selected?.default_input_unit_id ??
                                            0,
                                    });
                                }}
                                required
                            >
                                <option value="">Elegí uno</option>
                                {items.map((i) => (
                                    <option key={i.id} value={i.id}>
                                        {i.name}
                                    </option>
                                ))}
                            </select>
                        </div>
                        <div>
                            <Label htmlFor={`quantity-${quantity}-${n}`}>
                                Cantidad
                            </Label>
                            <Input
                                id={`quantity-${quantity}-${n}`}
                                aria-label={`Cantidad ${n} para ${sale}`}
                                inputMode="decimal"
                                value={c.quantity}
                                onChange={(e) =>
                                    change({ quantity: e.target.value })
                                }
                                required
                            />
                        </div>
                        <div>
                            <Label htmlFor={`unit-${quantity}-${n}`}>
                                Medida
                            </Label>
                            <select
                                id={`unit-${quantity}-${n}`}
                                aria-label={`Medida ${n} para ${sale}`}
                                className={selectClass}
                                value={c.unit_id || ''}
                                onChange={(e) =>
                                    change({ unit_id: Number(e.target.value) })
                                }
                                required
                            >
                                <option value="">Elegí una</option>
                                {units
                                    .filter(
                                        (u) =>
                                            !item ||
                                            u.family === item.unit_family,
                                    )
                                    .map((u) => (
                                        <option key={u.id} value={u.id}>
                                            {inventoryUnitName(u)}
                                        </option>
                                    ))}
                            </select>
                        </div>
                        <Button
                            type="button"
                            variant="outline"
                            aria-label={`Quitar ${item?.name ?? `caja o envoltorio ${n}`} de ${sale}`}
                            onClick={() =>
                                components.length === 1
                                    ? choose('unknown')
                                    : setComponents(
                                          components.filter(
                                              (_, k) => k !== index,
                                          ),
                                      )
                            }
                        >
                            Quitar
                        </Button>
                    </div>
                );
            })}
            {answer === 'components' && (
                <Button
                    type="button"
                    variant="outline"
                    className="justify-self-start"
                    onClick={() =>
                        setComponents([...components, emptyComponent])
                    }
                >
                    Agregar otro para {sale}
                </Button>
            )}
            {onRemove && (
                <Button
                    type="button"
                    variant="outline"
                    className="justify-self-start"
                    onClick={onRemove}
                >
                    Quitar {sale}
                </Button>
            )}
        </fieldset>
    );
}

export default function CostingPolicies({
    policies,
    items,
    units,
}: {
    policies: Policy[];
    items: Item[];
    units: Unit[];
}) {
    const { url } = usePage();
    const requested = Number(
        new URLSearchParams(url.split('?')[1] ?? '').get('edit'),
    );
    const initial = policies.find((p) => p.id === requested) ?? null;
    const [editing, setEditing] = useState<number | 'new' | null>(
        initial?.id ?? (policies.length === 0 ? 'new' : null),
    );
    const form = useForm<PolicyPayload>(payloadOf(initial));
    const [overhead, setOverhead] = useState(
        basisPointsToPercent(initial?.overhead_basis_points ?? 0),
    );
    const [extraQuantity, setExtraQuantity] = useState('');
    const overheadBasisPoints = percentToBasisPoints(overhead);

    function open(policy: Policy | null) {
        setEditing(policy?.id ?? 'new');
        form.setData(payloadOf(policy));
        setOverhead(basisPointsToPercent(policy?.overhead_basis_points ?? 0));
        form.clearErrors();
        requestAnimationFrame(() =>
            document
                .getElementById('packaging-editor')
                ?.scrollIntoView({ block: 'start' }),
        );
    }
    function setPresentation(
        quantity: number,
        presentation: PolicyPresentation | undefined,
    ) {
        const others = form.data.presentations.filter(
            (p) => p.commercial_quantity !== quantity,
        );
        form.setData(
            'presentations',
            [...others, ...(presentation ? [presentation] : [])].sort(
                (a, b) => a.commercial_quantity - b.commercial_quantity,
            ),
        );
    }
    const extraQuantities = form.data.presentations
        .map((p) => p.commercial_quantity)
        .filter((q) => !standardQuantities.includes(q));
    const newQuantity = Number(extraQuantity);
    const canAddQuantity =
        Number.isInteger(newQuantity) &&
        newQuantity >= 1 &&
        newQuantity <= 120 &&
        !standardQuantities.includes(newQuantity) &&
        !extraQuantities.includes(newQuantity);
    function save(event: React.FormEvent) {
        event.preventDefault();

        if (overheadBasisPoints === null) {
            form.setError(
                'overhead_basis_points',
                'Escribí un porcentaje entre 0 y 100, con hasta dos decimales.',
            );

            return;
        }

        form.transform((data) => ({
            ...data,
            overhead_basis_points: overheadBasisPoints,
        }));

        if (editing === 'new') {
            form.post('/admin/inventory/costing-policies', {
                onSuccess: () => setEditing(null),
            });
        } else {
            form.patch(`/admin/inventory/costing-policies/${editing}`, {
                onSuccess: () => setEditing(null),
            });
        }
    }

    return (
        <AdminPageShell
            title="Packaging y gastos generales"
            description="Qué caja o envoltorio lleva cada venta y cuánto sumar por gastos generales (gas, luz, alquiler). Después elegí cuál usa cada producto en Costos."
            actions={
                <>
                    <Button asChild variant="outline">
                        <Link href="/admin/inventory/costing">
                            Volver a Costos
                        </Link>
                    </Button>
                    {editing === null && (
                        <Button type="button" onClick={() => open(null)}>
                            Nuevo packaging y gastos
                        </Button>
                    )}
                </>
            }
        >
            <Head title="Packaging y gastos generales" />
            {editing !== null && (
                <Card
                    id="packaging-editor"
                    className="admin-ui admin-panel scroll-mt-4"
                >
                    <CardContent>
                        <form onSubmit={save} className="grid gap-4">
                            <h2 className="text-xl font-semibold">
                                {editing === 'new'
                                    ? 'Nuevo packaging y gastos'
                                    : `Editar ${form.data.name}`}
                            </h2>
                            <div>
                                <Label htmlFor="policy-name">Nombre</Label>
                                <Input
                                    id="policy-name"
                                    value={form.data.name}
                                    onChange={(e) =>
                                        form.setData('name', e.target.value)
                                    }
                                    placeholder="Por ejemplo: Empanadas"
                                    maxLength={160}
                                    required
                                />
                            </div>
                            <div>
                                <Label htmlFor="policy-overhead">
                                    Gastos generales (%)
                                </Label>
                                <Input
                                    id="policy-overhead"
                                    inputMode="decimal"
                                    value={overhead}
                                    onChange={(e) =>
                                        setOverhead(e.target.value)
                                    }
                                    aria-describedby="policy-overhead-help"
                                    required
                                />
                                <p
                                    id="policy-overhead-help"
                                    className="text-sm text-admin-text-secondary"
                                >
                                    Se suma al costo de insumos y packaging. Por
                                    ejemplo, 10 % sobre $ 1.000 son $ 100.
                                </p>
                            </div>
                            <label className="flex items-center gap-2">
                                <input
                                    type="checkbox"
                                    checked={form.data.is_active}
                                    onChange={(e) =>
                                        form.setData(
                                            'is_active',
                                            e.target.checked,
                                        )
                                    }
                                />
                                Activo
                            </label>
                            {standardQuantities.map((quantity) => (
                                <PackagingQuestion
                                    key={quantity}
                                    quantity={quantity}
                                    presentation={form.data.presentations.find(
                                        (p) =>
                                            p.commercial_quantity === quantity,
                                    )}
                                    items={items}
                                    units={units}
                                    onChange={(p) =>
                                        setPresentation(quantity, p)
                                    }
                                />
                            ))}
                            {extraQuantities.map((quantity) => (
                                <PackagingQuestion
                                    key={quantity}
                                    quantity={quantity}
                                    presentation={form.data.presentations.find(
                                        (p) =>
                                            p.commercial_quantity === quantity,
                                    )}
                                    items={items}
                                    units={units}
                                    onChange={(p) =>
                                        setPresentation(
                                            quantity,
                                            p ?? {
                                                commercial_quantity: quantity,
                                                packaging_mode: 'none',
                                                components: [],
                                            },
                                        )
                                    }
                                    onRemove={() =>
                                        setPresentation(quantity, undefined)
                                    }
                                />
                            ))}
                            <details>
                                <summary className="cursor-pointer">
                                    ¿Vendés por otra cantidad de unidades?
                                </summary>
                                <div className="mt-2 flex flex-wrap items-end gap-2">
                                    <div>
                                        <Label htmlFor="extra-quantity">
                                            Cantidad de unidades
                                        </Label>
                                        <Input
                                            id="extra-quantity"
                                            type="number"
                                            min={1}
                                            max={120}
                                            step={1}
                                            value={extraQuantity}
                                            onChange={(e) =>
                                                setExtraQuantity(e.target.value)
                                            }
                                        />
                                    </div>
                                    <Button
                                        type="button"
                                        variant="outline"
                                        disabled={!canAddQuantity}
                                        onClick={() => {
                                            setPresentation(newQuantity, {
                                                commercial_quantity:
                                                    newQuantity,
                                                packaging_mode: 'none',
                                                components: [],
                                            });
                                            setExtraQuantity('');
                                        }}
                                    >
                                        Agregar cantidad
                                    </Button>
                                </div>
                            </details>
                            <p className="text-sm text-admin-text-secondary">
                                Si todavía no sabés qué lleva una venta, su
                                costo queda sin calcular: nunca se toma como
                                cero.
                            </p>
                            {Object.keys(form.errors).length > 0 && (
                                <ul
                                    role="alert"
                                    className="break-words text-destructive"
                                >
                                    {Object.entries(form.errors).map(
                                        ([key, error]) => (
                                            <li key={key}>{error}</li>
                                        ),
                                    )}
                                </ul>
                            )}
                            <div className="flex flex-wrap gap-2">
                                <Button
                                    type="submit"
                                    disabled={form.processing}
                                >
                                    Guardar
                                </Button>
                                {policies.length > 0 && (
                                    <Button
                                        type="button"
                                        variant="outline"
                                        onClick={() => {
                                            setEditing(null);
                                            form.clearErrors();
                                        }}
                                    >
                                        Cancelar
                                    </Button>
                                )}
                            </div>
                        </form>
                    </CardContent>
                </Card>
            )}
            {policies.length === 0 && editing === null && (
                <p role="status">
                    Todavía no cargaste packaging y gastos. Creá uno y elegilo
                    para cada producto en Costos.
                </p>
            )}
            <div className="grid gap-3 lg:grid-cols-2">
                {policies.map((p) => (
                    <PolicySummary
                        key={p.id}
                        policy={p}
                        onEdit={() => open(p)}
                    />
                ))}
            </div>
        </AdminPageShell>
    );
}
