import { Head, Link, useForm } from '@inertiajs/react';
import { Trash2 } from 'lucide-react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    consumptionExample,
    formatInventoryQuantity,
    inventoryItemKindLabel,
    inventoryUnitName,
} from '@/features/inventory/presentation';

const selectClass =
    'h-10 w-full min-w-0 rounded-md border bg-admin-canvas px-3 text-sm';
const cellLabelClass = 'text-xs text-admin-text-secondary md:sr-only';
const componentColumns = 'md:grid-cols-[minmax(12rem,2fr)_10rem_10rem_2.75rem]';

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
        `${i.name} ${i.category.name} ${i.kind} ${inventoryItemKindLabel(i.kind)}`
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
                    ? 'Falta configuración: todavía no se sabe qué insumos usa este producto y sus pedidos no se van a poder entregar.'
                    : definition.mode === 'no_inventory'
                      ? 'Sin consumo de stock: este producto no descuenta insumos al entregarse.'
                      : 'Consumo configurado: los insumos se descuentan al entregar el pedido.'}
            </p>
            <p>
                Cargá cuánto de cada insumo usa 1 unidad vendida (por ejemplo, 1
                empanada), no el valor por docena: al vender una docena se
                multiplica por 12. Los rellenos hechos con receta se descuentan
                tal cual, sin desarmar la receta.
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
                                    Descuenta insumos del stock
                                </option>
                                <option value="no_inventory">
                                    No usa insumos con stock
                                </option>
                            </select>
                        </label>
                    )}
                    {form.data.mode === 'no_inventory' ? (
                        <p>
                            Elegí esto sólo si el producto realmente no usa
                            insumos con stock. Después no se va a poder cambiar.
                        </p>
                    ) : (
                        <>
                            <label>
                                Buscar insumo por nombre, categoría o
                                clasificación
                                <input
                                    className="block w-full rounded border p-2"
                                    value={search}
                                    onChange={(e) => setSearch(e.target.value)}
                                />
                            </label>
                            <div
                                aria-hidden="true"
                                className={`hidden gap-3 border-b border-admin-border px-2 pb-2 text-xs font-medium text-admin-text-secondary md:grid ${componentColumns}`}
                            >
                                <span>Insumo</span>
                                <span>Cantidad por unidad vendida</span>
                                <span>Unidad</span>
                                <span />
                            </div>
                            {form.data.components.map((c, index) => {
                                const n = index + 1;
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
                                const unit = units.find(
                                    (u) => String(u.id) === c.unit_id,
                                );
                                const example = unit
                                    ? consumptionExample(c.quantity, unit.code)
                                    : null;

                                return (
                                    <div
                                        key={index}
                                        role="group"
                                        aria-label={`Componente ${n}`}
                                        className={`grid grid-cols-2 gap-3 rounded-lg border border-admin-border bg-admin-surface p-3 md:items-start md:rounded-none md:border-0 md:border-b md:bg-transparent md:px-2 ${componentColumns}`}
                                    >
                                        <div className="col-span-2 grid gap-1 md:col-span-1">
                                            <label
                                                htmlFor={`component-item-${index}`}
                                                className={cellLabelClass}
                                            >
                                                Insumo
                                            </label>
                                            <select
                                                id={`component-item-${index}`}
                                                aria-label={`Insumo ${n}`}
                                                className={selectClass}
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
                                                    Elegir insumo
                                                </option>
                                                {choices.map((i) => (
                                                    <option
                                                        key={i.id}
                                                        value={i.id}
                                                    >
                                                        {i.name} ·{' '}
                                                        {i.category.name} ·{' '}
                                                        {inventoryItemKindLabel(
                                                            i.kind,
                                                        )}
                                                    </option>
                                                ))}
                                            </select>
                                        </div>
                                        <div className="grid gap-1">
                                            <label
                                                htmlFor={`component-quantity-${index}`}
                                                className={cellLabelClass}
                                            >
                                                Cantidad por unidad vendida
                                            </label>
                                            <Input
                                                id={`component-quantity-${index}`}
                                                aria-label={`Cantidad ${n}`}
                                                inputMode="decimal"
                                                value={c.quantity}
                                                onChange={(e) =>
                                                    change(index, {
                                                        quantity:
                                                            e.target.value,
                                                    })
                                                }
                                            />
                                        </div>
                                        <div className="grid gap-1">
                                            <label
                                                htmlFor={`component-unit-${index}`}
                                                className={cellLabelClass}
                                            >
                                                Unidad
                                            </label>
                                            <select
                                                id={`component-unit-${index}`}
                                                aria-label={`Unidad ${n}`}
                                                className={selectClass}
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
                                                            {inventoryUnitName(
                                                                u,
                                                            )}
                                                        </option>
                                                    ))}
                                            </select>
                                        </div>
                                        <div className="col-span-2 flex justify-end md:col-span-1">
                                            <Button
                                                type="button"
                                                variant="ghost"
                                                size="icon"
                                                aria-label={`Quitar componente ${n}`}
                                                title={`Quitar componente ${n}`}
                                                onClick={() =>
                                                    form.setData(
                                                        'components',
                                                        form.data.components.filter(
                                                            (_, i) =>
                                                                i !== index,
                                                        ),
                                                    )
                                                }
                                            >
                                                <Trash2 aria-hidden="true" />
                                            </Button>
                                        </div>
                                        {example && (
                                            <p
                                                className="col-span-2 text-sm text-admin-text-secondary md:col-span-4"
                                                data-testid={`consumption-example-${n}`}
                                            >
                                                {example}
                                            </p>
                                        )}
                                    </div>
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
                            Al guardar, la versión actual pasa a «Retiradas»;
                            los pedidos ya entregados no cambian.
                        </p>
                    )}
                </form>
            )}
        </section>
    );
}
