import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { FormField } from '@/features/inventory/components/form-field';
import { formatInventoryQuantity } from '@/features/inventory/presentation';
import {
    costCoverageLabels,
    formatRecipeMoney as money,
} from '@/features/inventory/recipes/presentation';
import type { RecipeVersion } from '@/features/inventory/recipes/types';
import type { InventoryUnit } from '@/features/inventory/types';
import { Head, Link, useForm } from '@inertiajs/react';
import { useState } from 'react';
export default function CreateProduction({
    versions,
    units,
    business_today,
}: {
    versions: RecipeVersion[];
    units: InventoryUnit[];
    business_today: string;
}) {
    const [search, setSearch] = useState('');
    const [key] = useState(() => crypto.randomUUID());
    const form = useForm({
        recipe_version_id: String(versions.length === 1 ? versions[0].id : ''),
        output_unit_id: String(
            versions.length === 1 ? versions[0].output_unit_id : '',
        ),
        actual_output_quantity: '',
        effective_date: business_today,
        idempotency_key: key,
        notes: '',
    });
    const version = versions.find(
        (v) => String(v.id) === form.data.recipe_version_id,
    );

    return (
        <section className="grid gap-6 text-admin-text-primary">
            <Head title="Registrar producción" />
            <Link href="/admin/inventory/production">Volver a Producción</Link>
            <h1 className="text-2xl font-semibold">Registrar producción</h1>
            {!versions.length && (
                <p role="status">
                    Se necesita una receta vigente.{' '}
                    <Link
                        className="underline"
                        href="/admin/inventory/recipes/create"
                    >
                        Crear receta
                    </Link>
                    .
                </p>
            )}
            <form
                className="grid gap-5"
                onSubmit={(e) => {
                    e.preventDefault();
                    form.post('/admin/inventory/production');
                }}
            >
                <FormField id="production-search" label="Buscar receta">
                    <Input
                        id="production-search"
                        type="search"
                        value={search}
                        onChange={(e) => setSearch(e.target.value)}
                    />
                </FormField>
                <FormField id="production-version" label="Receta vigente">
                    <select
                        id="production-version"
                        className="min-h-11 w-full rounded-md border border-admin-border bg-admin-canvas px-3"
                        value={form.data.recipe_version_id}
                        required
                        onChange={(e) => {
                            const v = versions.find(
                                (v) => String(v.id) === e.target.value,
                            );
                            form.setData({
                                ...form.data,
                                recipe_version_id: e.target.value,
                                output_unit_id: String(v?.output_unit_id ?? ''),
                            });
                        }}
                    >
                        <option value="">Seleccionar receta</option>
                        {versions
                            .filter(
                                (v) =>
                                    v.id === version?.id ||
                                    v.recipe_name_snapshot
                                        .toLocaleLowerCase()
                                        .includes(search.toLocaleLowerCase()),
                            )
                            .map((v) => (
                                <option key={v.id} value={v.id}>
                                    {v.recipe_name_snapshot} · v{v.version}
                                </option>
                            ))}
                    </select>
                </FormField>
                {version && (
                    <aside className="grid gap-2 rounded-lg border border-admin-border p-4">
                        <p>
                            Salida esperada:{' '}
                            {formatInventoryQuantity(
                                version.expected_output_quantity,
                                version.output_unit,
                            )}
                        </p>
                        <ul>
                            {version.ingredients.map((i) => (
                                <li key={i.id}>
                                    {i.item.name}:{' '}
                                    {formatInventoryQuantity(
                                        i.quantity,
                                        i.unit,
                                    )}
                                </li>
                            ))}
                        </ul>
                        <p>
                            Estimación actual:{' '}
                            {money(version.current_cost.estimated_total_cost)} ·{' '}
                            {costCoverageLabels[version.current_cost.status]}
                        </p>
                        <p className="text-sm">
                            Un lote consume esta fórmula una vez. La salida real
                            no escala los ingredientes; cambia el costo por
                            unidad producida.
                        </p>
                    </aside>
                )}
                <div className="grid gap-4 sm:grid-cols-3">
                    <FormField
                        id="production-actual"
                        label="Cantidad real producida"
                    >
                        <Input
                            id="production-actual"
                            inputMode="decimal"
                            value={form.data.actual_output_quantity}
                            required
                            onChange={(e) =>
                                form.setData(
                                    'actual_output_quantity',
                                    e.target.value,
                                )
                            }
                        />
                    </FormField>
                    <FormField id="production-unit" label="Unidad producida">
                        <select
                            id="production-unit"
                            className="min-h-11 w-full rounded-md border border-admin-border bg-admin-canvas px-3"
                            required
                            value={form.data.output_unit_id}
                            onChange={(e) =>
                                form.setData('output_unit_id', e.target.value)
                            }
                        >
                            <option value="">Seleccionar unidad</option>
                            {units
                                .filter(
                                    (u) =>
                                        u.family ===
                                        version?.output_item.unit_family,
                                )
                                .map((u) => (
                                    <option key={u.id} value={u.id}>
                                        {u.code}
                                    </option>
                                ))}
                        </select>
                    </FormField>
                    <FormField id="production-date" label="Fecha de producción">
                        <Input
                            id="production-date"
                            type="date"
                            value={form.data.effective_date}
                            required
                            onChange={(e) =>
                                form.setData('effective_date', e.target.value)
                            }
                        />
                    </FormField>
                </div>
                <FormField id="production-notes" label="Notas">
                    <Input
                        id="production-notes"
                        maxLength={240}
                        value={form.data.notes}
                        onChange={(e) => form.setData('notes', e.target.value)}
                    />
                </FormField>
                {Object.keys(form.errors).length > 0 && (
                    <ul role="alert">
                        {Object.entries(form.errors).map(([k, v]) => (
                            <li key={k}>{v}</li>
                        ))}
                    </ul>
                )}
                <Button type="submit" disabled={form.processing || !version}>
                    {form.processing ? 'Registrando…' : 'Confirmar producción'}
                </Button>
            </form>
        </section>
    );
}
