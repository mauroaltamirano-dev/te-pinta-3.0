import { Head, Link, useForm } from '@inertiajs/react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
import { formatInventoryQuantity } from '@/features/inventory/presentation';

type Unit = { id: number; code: string; family: string };
type Item = {
    id: number;
    name: string;
    unit_family: string;
    default_input_unit_id: number;
    category: { name: string };
    kind: string;
};
type Component = {
    inventory_item_id: number;
    unit_id: number;
    quantity: string;
    item: { name: string };
    unit?: Unit;
};
type Version = {
    id: number;
    version: number;
    is_active: boolean;
    components: Component[];
};
type Definition = {
    mode: 'consume' | 'no_inventory';
    current_version: Version | null;
    versions: Version[];
};

export default function ProductConsumption({
    product,
    definition,
    items,
    units,
}: {
    product: { id: number; name: string };
    definition: Definition | null;
    items: Item[];
    units: Unit[];
}) {
    const [view, setView] = useState<'current' | 'retired'>('current');
    const [search, setSearch] = useState('');
    const form = useForm<{
        mode: 'consume' | 'no_inventory';
        components: {
            inventory_item_id: string;
            unit_id: string;
            quantity: string;
        }[];
    }>({
        mode: definition?.mode ?? 'consume',
        components:
            definition?.current_version?.components.map((c) => ({
                inventory_item_id: String(c.inventory_item_id),
                unit_id: String(c.unit_id),
                quantity: c.quantity,
            })) ?? [],
    });
    const eligible = items.filter((i) =>
        `${i.name} ${i.category.name} ${i.kind}`
            .toLocaleLowerCase()
            .includes(search.toLocaleLowerCase()),
    );
    const versions =
        definition?.versions.filter((v) =>
            view === 'current' ? v.is_active : !v.is_active,
        ) ?? [];
    function change(
        index: number,
        value: Partial<(typeof form.data.components)[number]>,
    ) {
        form.setData(
            'components',
            form.data.components.map((c, n) =>
                n === index ? { ...c, ...value } : c,
            ),
        );
    }

    return (
        <section className="grid max-w-3xl gap-5 text-admin-text-primary">
            <Head title={`Consumo físico · ${product.name}`} />
            <Link href="/admin/settings/products" className="underline">
                Volver a Productos
            </Link>
            <h1 className="text-2xl font-semibold">
                Consumo físico · {product.name}
            </h1>
            <p>
                {!definition
                    ? 'Falta configuración: no se conoce el consumo y la entrega quedará bloqueada.'
                    : definition.mode === 'no_inventory'
                      ? 'Sin inventario explícito: costo físico conocido igual a cero.'
                      : 'Consumo configurado: se descuenta al entregar el pedido.'}
            </p>
            <p>
                Se consumen los componentes configurados. No se expanden recetas
                ni se modifican precios de venta.
            </p>
            {definition?.mode === 'consume' && (
                <>
                    <div className="flex gap-2">
                        <Button
                            type="button"
                            variant={view === 'current' ? 'default' : 'outline'}
                            onClick={() => setView('current')}
                        >
                            Actual
                        </Button>
                        <Button
                            type="button"
                            variant={view === 'retired' ? 'default' : 'outline'}
                            onClick={() => setView('retired')}
                        >
                            Retiradas
                        </Button>
                    </div>
                    {versions.length === 0 && (
                        <p>No hay versiones retiradas.</p>
                    )}
                    {versions.map((v) => (
                        <article key={v.id} className="rounded border p-4">
                            <h2>
                                Versión {v.version} ·{' '}
                                {v.is_active ? 'Actual' : 'Retirada'}
                            </h2>
                            <ul>
                                {v.components.map((c) => (
                                    <li key={c.inventory_item_id}>
                                        {c.item.name} —{' '}
                                        {formatInventoryQuantity(
                                            c.quantity,
                                            c.unit?.code ??
                                                units.find(
                                                    (u) => u.id === c.unit_id,
                                                )?.code ??
                                                '',
                                        )}
                                    </li>
                                ))}
                            </ul>
                        </article>
                    ))}
                </>
            )}
            {definition?.mode !== 'no_inventory' && (
                <form
                    className="grid gap-4"
                    onSubmit={(e) => {
                        e.preventDefault();
                        form.post(
                            `/admin/inventory/product-consumption/${product.id}`,
                        );
                    }}
                >
                    <h2 className="text-lg font-semibold">
                        {definition
                            ? 'Crear nueva versión'
                            : 'Configurar producto'}
                    </h2>
                    {!definition && (
                        <label>
                            Estado
                            <select
                                className="block w-full rounded border p-2"
                                value={form.data.mode}
                                onChange={(e) =>
                                    form.setData({
                                        mode: e.target.value as
                                            'consume' | 'no_inventory',
                                        components: [],
                                    })
                                }
                            >
                                <option value="consume">
                                    Consumir inventario
                                </option>
                                <option value="no_inventory">
                                    Sin inventario (decisión explícita)
                                </option>
                            </select>
                        </label>
                    )}
                    {form.data.mode === 'no_inventory' ? (
                        <p>
                            Esto registra cero físico conocido, no configuración
                            pendiente. El modo no podrá cambiar después.
                        </p>
                    ) : (
                        <>
                            <label>
                                Buscar por nombre, categoría o clasificación
                                <input
                                    className="block w-full rounded border p-2"
                                    value={search}
                                    onChange={(e) => setSearch(e.target.value)}
                                />
                            </label>
                            {form.data.components.map((c, index) => {
                                const item = items.find(
                                    (i) => String(i.id) === c.inventory_item_id,
                                );
                                const choices =
                                    eligible.some(
                                        (i) =>
                                            String(i.id) ===
                                            c.inventory_item_id,
                                    ) || !item
                                        ? eligible
                                        : [item, ...eligible];

                                return (
                                    <fieldset
                                        key={index}
                                        className="grid gap-2 rounded border p-3"
                                    >
                                        <legend>Componente {index + 1}</legend>
                                        <label>
                                            Item
                                            <select
                                                aria-label={`Item ${index + 1}`}
                                                className="block w-full rounded border p-2"
                                                value={c.inventory_item_id}
                                                onChange={(e) => {
                                                    const selected = items.find(
                                                        (i) =>
                                                            String(i.id) ===
                                                            e.target.value,
                                                    );
                                                    change(index, {
                                                        inventory_item_id:
                                                            e.target.value,
                                                        unit_id: String(
                                                            selected?.default_input_unit_id ??
                                                                '',
                                                        ),
                                                    });
                                                }}
                                            >
                                                <option value="">
                                                    Elegir item apto
                                                </option>
                                                {choices.map((i) => (
                                                    <option
                                                        key={i.id}
                                                        value={i.id}
                                                    >
                                                        {i.name} ·{' '}
                                                        {i.category.name} ·{' '}
                                                        {i.kind}
                                                    </option>
                                                ))}
                                            </select>
                                        </label>
                                        <label>
                                            Cantidad por unidad vendida
                                            <input
                                                aria-label={`Cantidad ${index + 1}`}
                                                className="block w-full rounded border p-2"
                                                inputMode="decimal"
                                                value={c.quantity}
                                                onChange={(e) =>
                                                    change(index, {
                                                        quantity:
                                                            e.target.value,
                                                    })
                                                }
                                            />
                                        </label>
                                        <label>
                                            Unidad
                                            <select
                                                aria-label={`Unidad ${index + 1}`}
                                                className="block w-full rounded border p-2"
                                                value={c.unit_id}
                                                onChange={(e) =>
                                                    change(index, {
                                                        unit_id: e.target.value,
                                                    })
                                                }
                                            >
                                                <option value="">
                                                    Elegir unidad
                                                </option>
                                                {units
                                                    .filter(
                                                        (u) =>
                                                            u.family ===
                                                            item?.unit_family,
                                                    )
                                                    .map((u) => (
                                                        <option
                                                            key={u.id}
                                                            value={u.id}
                                                        >
                                                            {u.code}
                                                        </option>
                                                    ))}
                                            </select>
                                        </label>
                                        <Button
                                            type="button"
                                            variant="outline"
                                            onClick={() =>
                                                form.setData(
                                                    'components',
                                                    form.data.components.filter(
                                                        (_, n) => n !== index,
                                                    ),
                                                )
                                            }
                                        >
                                            Quitar componente {index + 1}
                                        </Button>
                                    </fieldset>
                                );
                            })}
                            <Button
                                type="button"
                                variant="outline"
                                onClick={() =>
                                    form.setData('components', [
                                        ...form.data.components,
                                        {
                                            inventory_item_id: '',
                                            unit_id: '',
                                            quantity: '',
                                        },
                                    ])
                                }
                            >
                                Agregar componente
                            </Button>
                        </>
                    )}
                    <div role="alert">
                        {Object.entries(form.errors).map(([key, message]) => (
                            <p key={key}>{message}</p>
                        ))}
                    </div>
                    <Button
                        type="submit"
                        disabled={
                            form.processing ||
                            (form.data.mode === 'consume' &&
                                form.data.components.length === 0)
                        }
                    >
                        Guardar configuración
                    </Button>
                    {definition && (
                        <p>
                            La versión anterior se retira sin cambiar pedidos
                            históricos.
                        </p>
                    )}
                </form>
            )}
        </section>
    );
}
