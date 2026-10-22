import { Head, Link, useForm } from '@inertiajs/react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { percentage } from '@/features/inventory/costing/types';
import type {
    Policy,
    PolicyPayload,
    PolicyPresentation,
} from '@/features/inventory/costing/types';
import {
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
const empty: PolicyPayload = {
    name: '',
    overhead_basis_points: 0,
    is_active: true,
    presentations: [],
};
const selectClass =
    'w-full rounded-md border border-admin-border bg-admin-surface p-2';

export default function CostingPolicies({
    policies,
    items,
    units,
}: {
    policies: Policy[];
    items: Item[];
    units: Unit[];
}) {
    const [editing, setEditing] = useState<number | null>(null);
    const form = useForm<PolicyPayload>(empty);
    function edit(policy: Policy) {
        setEditing(policy.id);
        form.setData({
            name: policy.name,
            overhead_basis_points: policy.overhead_basis_points,
            is_active: policy.is_active,
            presentations: policy.presentations.map((p) => ({
                commercial_quantity: p.commercial_quantity,
                packaging_mode: p.packaging_mode,
                components: p.components.map((c) => ({
                    inventory_item_id: c.inventory_item_id,
                    quantity: c.quantity,
                    unit_id: c.unit_id,
                })),
            })),
        });
        form.clearErrors();
    }
    function update(index: number, change: Partial<PolicyPresentation>) {
        form.setData(
            'presentations',
            form.data.presentations.map((p, n) =>
                n === index ? { ...p, ...change } : p,
            ),
        );
    }
    function save(event: React.FormEvent) {
        event.preventDefault();

        if (editing === null) {
            form.post('/admin/inventory/costing-policies', {
                onSuccess: () => form.reset(),
            });
        } else {
            form.patch(`/admin/inventory/costing-policies/${editing}`);
        }
    }

    return (
        <section className="grid gap-6 text-admin-text-primary">
            <Head title="Políticas de costo" />
            <header className="space-y-2">
                <h1 className="text-2xl font-semibold">
                    Políticas de costo estimado
                </h1>
                <p>
                    Packaging y costos indirectos para estimaciones actuales. No
                    registran consumo físico ni gastos financieros.
                </p>
                <nav className="flex flex-wrap gap-4">
                    <Link className="underline" href="/admin/inventory/costing">
                        Costo actual de productos
                    </Link>
                    <Link className="underline" href="/admin/inventory">
                        Inventario
                    </Link>
                </nav>
            </header>
            <div className="grid gap-3">
                {policies.length === 0 && (
                    <p role="status">
                        No hay políticas. Creá una política y asignala
                        explícitamente a un producto.
                    </p>
                )}
                {policies.map((p) => (
                    <article
                        key={p.id}
                        className="grid gap-2 rounded-xl border border-admin-border p-4"
                    >
                        <h2 className="font-semibold">
                            {p.name} · {p.is_active ? 'Activa' : 'Inactiva'}
                        </h2>
                        <p>
                            Costos indirectos:{' '}
                            {percentage(p.overhead_basis_points)}
                        </p>
                        {p.presentations.map((r) => (
                            <div key={r.commercial_quantity}>
                                <p>
                                    {r.commercial_quantity}{' '}
                                    {r.commercial_quantity === 1
                                        ? 'unidad comercial'
                                        : 'unidades comerciales'}
                                    :{' '}
                                    {r.packaging_mode === 'none'
                                        ? 'No requiere packaging'
                                        : 'Packaging configurado'}
                                </p>
                                {r.components.map((c) => (
                                    <p key={c.inventory_item_id}>
                                        {c.item?.name} ·{' '}
                                        {formatInventoryQuantity(
                                            c.quantity,
                                            c.unit?.code ?? '',
                                        )}
                                    </p>
                                ))}
                            </div>
                        ))}
                        {!p.presentations.length && (
                            <p>Packaging sin configurar.</p>
                        )}
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => edit(p)}
                        >
                            Editar {p.name}
                        </Button>
                    </article>
                ))}
            </div>
            <form
                onSubmit={save}
                className="grid gap-4 rounded-xl border border-admin-border p-4"
            >
                <h2 className="text-xl font-semibold">
                    {editing === null ? 'Crear política' : 'Editar política'}
                </h2>
                <div>
                    <Label htmlFor="policy-name">Nombre de la política</Label>
                    <Input
                        id="policy-name"
                        value={form.data.name}
                        onChange={(e) => form.setData('name', e.target.value)}
                        maxLength={160}
                        required
                    />
                </div>
                <div>
                    <Label htmlFor="policy-overhead">
                        Costos indirectos estimados (basis points)
                    </Label>
                    <Input
                        id="policy-overhead"
                        type="number"
                        min={0}
                        max={10000}
                        step={1}
                        value={form.data.overhead_basis_points}
                        onChange={(e) =>
                            form.setData(
                                'overhead_basis_points',
                                Number(e.target.value),
                            )
                        }
                        required
                    />
                    <p>
                        {percentage(form.data.overhead_basis_points)} · 1000 bp
                        = 10 %. Se aplica sobre costo directo + packaging.
                    </p>
                </div>
                <label className="flex items-center gap-2">
                    <input
                        type="checkbox"
                        checked={form.data.is_active}
                        onChange={(e) =>
                            form.setData('is_active', e.target.checked)
                        }
                    />
                    Política activa
                </label>
                {form.data.presentations.map((p, index) => (
                    <fieldset
                        key={index}
                        className="grid min-w-0 gap-3 rounded-md border border-admin-border p-3"
                    >
                        <legend>Presentación {index + 1}</legend>
                        <div>
                            <Label htmlFor={`commercial-${index}`}>
                                Unidades comerciales {index + 1}
                            </Label>
                            <Input
                                id={`commercial-${index}`}
                                type="number"
                                min={1}
                                max={120}
                                step={1}
                                value={p.commercial_quantity}
                                onChange={(e) =>
                                    update(index, {
                                        commercial_quantity: Number(
                                            e.target.value,
                                        ),
                                    })
                                }
                                required
                            />
                        </div>
                        <div>
                            <Label htmlFor={`mode-${index}`}>
                                Packaging {index + 1}
                            </Label>
                            <select
                                id={`mode-${index}`}
                                className={selectClass}
                                value={p.packaging_mode}
                                onChange={(e) =>
                                    update(index, {
                                        packaging_mode: e.target
                                            .value as PolicyPresentation['packaging_mode'],
                                        components: [],
                                    })
                                }
                            >
                                <option value="none">
                                    No requiere packaging
                                </option>
                                <option value="components">
                                    Requiere items de packaging
                                </option>
                            </select>
                        </div>
                        {p.components.map((c, ci) => {
                            const item = items.find(
                                (i) => i.id === c.inventory_item_id,
                            );
                            function change(values: Partial<typeof c>) {
                                update(index, {
                                    components: p.components.map((row, n) =>
                                        n === ci ? { ...row, ...values } : row,
                                    ),
                                });
                            }

                            return (
                                <div
                                    key={ci}
                                    className="grid gap-2 sm:grid-cols-3"
                                >
                                    <div>
                                        <Label htmlFor={`item-${index}-${ci}`}>
                                            Item {index + 1}.{ci + 1}
                                        </Label>
                                        <select
                                            id={`item-${index}-${ci}`}
                                            className={selectClass}
                                            value={c.inventory_item_id || ''}
                                            onChange={(e) => {
                                                const selected = items.find(
                                                    (i) =>
                                                        i.id ===
                                                        Number(e.target.value),
                                                );
                                                change({
                                                    inventory_item_id:
                                                        selected?.id ?? 0,
                                                    unit_id:
                                                        selected?.default_input_unit_id ??
                                                        0,
                                                });
                                            }}
                                            required
                                        >
                                            <option value="">
                                                Seleccionar item
                                            </option>
                                            {items.map((i) => (
                                                <option key={i.id} value={i.id}>
                                                    {i.name}
                                                </option>
                                            ))}
                                        </select>
                                    </div>
                                    <div>
                                        <Label
                                            htmlFor={`quantity-${index}-${ci}`}
                                        >
                                            Cantidad {index + 1}.{ci + 1}
                                        </Label>
                                        <Input
                                            id={`quantity-${index}-${ci}`}
                                            inputMode="decimal"
                                            value={c.quantity}
                                            onChange={(e) =>
                                                change({
                                                    quantity: e.target.value,
                                                })
                                            }
                                            required
                                        />
                                    </div>
                                    <div>
                                        <Label htmlFor={`unit-${index}-${ci}`}>
                                            Unidad {index + 1}.{ci + 1}
                                        </Label>
                                        <select
                                            id={`unit-${index}-${ci}`}
                                            className={selectClass}
                                            value={c.unit_id || ''}
                                            onChange={(e) =>
                                                change({
                                                    unit_id: Number(
                                                        e.target.value,
                                                    ),
                                                })
                                            }
                                            required
                                        >
                                            <option value="">
                                                Seleccionar unidad
                                            </option>
                                            {units
                                                .filter(
                                                    (u) =>
                                                        !item ||
                                                        u.family ===
                                                            item.unit_family,
                                                )
                                                .map((u) => (
                                                    <option
                                                        key={u.id}
                                                        value={u.id}
                                                    >
                                                        {inventoryUnitName(u)}
                                                    </option>
                                                ))}
                                        </select>
                                    </div>
                                    <Button
                                        type="button"
                                        variant="outline"
                                        onClick={() =>
                                            update(index, {
                                                components: p.components.filter(
                                                    (_, n) => n !== ci,
                                                ),
                                            })
                                        }
                                    >
                                        Quitar item {index + 1}.{ci + 1}
                                    </Button>
                                </div>
                            );
                        })}
                        {p.packaging_mode === 'components' && (
                            <Button
                                type="button"
                                variant="outline"
                                onClick={() =>
                                    update(index, {
                                        components: [
                                            ...p.components,
                                            {
                                                inventory_item_id: 0,
                                                quantity: '1',
                                                unit_id: 0,
                                            },
                                        ],
                                    })
                                }
                            >
                                Agregar item a presentación {index + 1}
                            </Button>
                        )}
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() =>
                                form.setData(
                                    'presentations',
                                    form.data.presentations.filter(
                                        (_, n) => n !== index,
                                    ),
                                )
                            }
                        >
                            Quitar presentación {index + 1}
                        </Button>
                    </fieldset>
                ))}
                <Button
                    type="button"
                    variant="outline"
                    onClick={() =>
                        form.setData('presentations', [
                            ...form.data.presentations,
                            {
                                commercial_quantity: 1,
                                packaging_mode: 'none',
                                components: [],
                            },
                        ])
                    }
                >
                    Agregar presentación
                </Button>
                <p>
                    La ausencia de una presentación significa packaging sin
                    configurar, no costo cero.
                </p>
                {Object.keys(form.errors).length > 0 && (
                    <ul role="alert" className="break-words text-danger">
                        {Object.entries(form.errors).map(([key, error]) => (
                            <li key={key}>{error}</li>
                        ))}
                    </ul>
                )}
                <div className="flex flex-wrap gap-2">
                    <Button type="submit" disabled={form.processing}>
                        Guardar política
                    </Button>
                    {editing !== null && (
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => {
                                setEditing(null);
                                form.setData(empty);
                                form.clearErrors();
                            }}
                        >
                            Crear otra política
                        </Button>
                    )}
                </div>
            </form>
        </section>
    );
}
