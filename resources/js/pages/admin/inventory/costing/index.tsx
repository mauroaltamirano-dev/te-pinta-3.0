import { Head, Link, router, useForm } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { percentage } from '@/features/inventory/costing/types';
import type { CostLine, Economics } from '@/features/inventory/costing/types';
import { formatInventoryQuantity } from '@/features/inventory/presentation';
import { formatMoney } from '@/lib/format';

type PolicyOption = { id: number; name: string };
const statusNames = {
    complete: 'Costo completo',
    incomplete: 'Costo incompleto',
    unreliable: 'Costo no confiable',
};
const physicalNames = {
    consume: 'Consume inventario',
    no_inventory: 'Sin consumo físico',
    missing_config: 'Consumo sin configurar',
};
const reasons: Record<string, string> = {
    consumption_missing: 'Configurar consumo físico.',
    policy_missing: 'Asignar una política de costo.',
    policy_inactive: 'La política asignada está inactiva.',
    packaging_missing:
        'Configurar packaging o declarar que no se requiere para esta presentación.',
    direct_cost_incomplete:
        'Falta valoración actual de componentes del consumo.',
    direct_cost_unreliable: 'La valoración del consumo no es confiable.',
    packaging_cost_incomplete:
        'Falta valoración actual o un item de packaging dejó de ser elegible.',
    packaging_cost_unreliable: 'La valoración del packaging no es confiable.',
    pricing_unavailable: 'No hay un precio vendible disponible en el backend.',
    no_current_valuation: 'Valoración actual desconocida',
    negative_stock: 'Stock negativo',
    partial_valuation: 'Valoración parcial',
    unreliable_valuation: 'Valoración no confiable',
    insufficient_stock: 'Stock insuficiente para una estimación confiable',
    packaging_item_ineligible: 'Item de packaging inactivo o no elegible',
};
function money(value: number | null) {
    return value === null ? 'No disponible' : formatMoney(value);
}
function CostComponents({
    title,
    lines,
}: {
    title: string;
    lines: CostLine[];
}) {
    if (!lines.length) {
        return null;
    }

    return (
        <details className="min-w-0">
            <summary className="cursor-pointer">{title}</summary>
            <ul className="mt-2 grid gap-2">
                {lines.map((l) => (
                    <li key={l.inventory_item_id} className="break-words">
                        {l.item_name}:{' '}
                        {formatInventoryQuantity(l.quantity, l.unit_code)} ·{' '}
                        {money(l.estimated_cost)}
                        {l.warning && (
                            <span> · {reasons[l.warning] ?? l.warning}</span>
                        )}
                    </li>
                ))}
            </ul>
        </details>
    );
}
function ProductEconomics({
    economics: e,
    policies,
}: {
    economics: Economics;
    policies: PolicyOption[];
}) {
    const form = useForm<{ costing_policy_id: number | null }>({
        costing_policy_id: e.costing_policy?.id ?? null,
    });

    return (
        <div className="grid gap-5">
            <header>
                <h2 className="text-xl font-semibold">{e.product_name}</h2>
                <p>
                    Familia de precio:{' '}
                    {e.pricing_family === 'empanada' ? 'Empanada' : 'Unidad'} ·{' '}
                    {physicalNames[e.physical_status]}
                </p>
                <p>
                    Política:{' '}
                    {e.costing_policy?.name ?? 'Sin política asignada'}
                    {e.policy_status === 'inactive' && ' (inactiva)'}
                </p>
                <Link
                    className="underline"
                    href={`/admin/inventory/product-consumption/${e.product_id}`}
                >
                    Configurar consumo físico
                </Link>
            </header>
            <form
                className="grid gap-2 sm:grid-cols-[1fr_auto]"
                onSubmit={(event) => {
                    event.preventDefault();
                    form.post(
                        `/admin/inventory/costing-policies/assignments/${e.product_id}`,
                    );
                }}
            >
                <div>
                    <Label htmlFor="costing-policy">
                        Política asignada al producto
                    </Label>
                    <select
                        id="costing-policy"
                        className="w-full rounded-md border border-admin-border bg-admin-surface p-2"
                        value={form.data.costing_policy_id ?? ''}
                        onChange={(event) =>
                            form.setData(
                                'costing_policy_id',
                                event.target.value === ''
                                    ? null
                                    : Number(event.target.value),
                            )
                        }
                    >
                        <option value="">Sin política</option>
                        {e.policy_status === 'inactive' && (
                            <option value={e.costing_policy?.id} disabled>
                                {e.costing_policy?.name} (inactiva)
                            </option>
                        )}
                        {policies.map((p) => (
                            <option key={p.id} value={p.id}>
                                {p.name}
                            </option>
                        ))}
                    </select>
                </div>
                <Button
                    type="submit"
                    className="self-end"
                    disabled={form.processing}
                >
                    Guardar asignación
                </Button>
                {form.errors.costing_policy_id && (
                    <p role="alert">{form.errors.costing_policy_id}</p>
                )}
            </form>
            {e.pricing_family === 'unit' && (
                <form
                    className="flex flex-wrap items-end gap-2"
                    action="/admin/inventory/costing"
                    method="get"
                >
                    <input
                        type="hidden"
                        name="product_id"
                        value={e.product_id}
                    />
                    <div>
                        <Label htmlFor="sale-quantity">
                            Cantidad comercial
                        </Label>
                        <Input
                            id="sale-quantity"
                            name="quantity"
                            type="number"
                            min={1}
                            max={120}
                            step={1}
                            defaultValue={
                                e.presentations[0].commercial_quantity
                            }
                            required
                        />
                    </div>
                    <Button type="submit" variant="outline">
                        Actualizar estimación
                    </Button>
                </form>
            )}
            <div className="grid min-w-0 gap-4 lg:grid-cols-3">
                {e.presentations.map((p) => (
                    <article
                        key={p.commercial_quantity}
                        className="grid min-w-0 content-start gap-3 rounded-xl border border-admin-border p-4"
                        data-testid="economics-presentation"
                    >
                        <h3 className="font-semibold">
                            {p.pricing_mode === 'half_dozen'
                                ? 'Media docena'
                                : p.pricing_mode === 'dozen'
                                  ? 'Docena'
                                  : p.commercial_quantity === 1
                                    ? 'Unidad'
                                    : `${p.commercial_quantity} unidades`}
                        </h3>
                        <p role="status">{statusNames[p.status]}</p>
                        <dl className="grid gap-2">
                            {[
                                [
                                    'Precio de venta (cocinado)',
                                    money(p.selling_price),
                                ],
                                ['Costo directo', money(p.direct_cost)],
                                ['Packaging', money(p.packaging_cost)],
                                [
                                    'Costos indirectos estimados',
                                    money(p.overhead_estimate),
                                ],
                                [
                                    'Costo total estimado',
                                    money(p.estimated_total_cost),
                                ],
                                ['Margen estimado', money(p.estimated_margin)],
                                [
                                    'Margen sobre venta',
                                    percentage(
                                        p.estimated_margin_percentage_bps,
                                    ),
                                ],
                            ].map(([label, value]) => (
                                <div
                                    key={label}
                                    className="flex flex-wrap justify-between gap-x-2"
                                >
                                    <dt>{label}</dt>
                                    <dd>{value}</dd>
                                </div>
                            ))}
                        </dl>
                        <p>
                            {p.packaging_status === 'none'
                                ? 'No requiere packaging'
                                : p.packaging_status === 'missing'
                                  ? 'Packaging sin configurar'
                                  : 'Packaging configurado'}
                        </p>
                        <p>
                            Cobertura directa:{' '}
                            {percentage(p.direct_coverage.percentage_bps)} ·
                            Packaging:{' '}
                            {percentage(p.packaging_coverage.percentage_bps)}
                        </p>
                        {!!p.warnings.length && (
                            <div>
                                <h4 className="font-semibold">
                                    Para calcular con confianza:
                                </h4>
                                <ul className="list-inside list-disc">
                                    {p.warnings.map((w) => (
                                        <li key={w}>{reasons[w] ?? w}</li>
                                    ))}
                                </ul>
                            </div>
                        )}
                        <CostComponents
                            title="Componentes del costo directo"
                            lines={p.direct_components}
                        />
                        <CostComponents
                            title="Componentes de packaging"
                            lines={p.packaging_components}
                        />
                    </article>
                ))}
            </div>
        </div>
    );
}
export default function CurrentCosting({
    products,
    selectedProductId,
    economics,
    policies,
}: {
    products: {
        id: number;
        name: string;
        pricing_family: 'empanada' | 'unit';
    }[];
    selectedProductId: number | null;
    economics: Economics | null;
    policies: PolicyOption[];
}) {
    return (
        <section className="grid gap-6 text-admin-text-primary">
            <Head title="Costo actual estimado" />
            <header className="space-y-2">
                <h1 className="text-2xl font-semibold">
                    Costo actual estimado de productos
                </h1>
                <p>
                    Estimación actual, no COGS histórico ni ganancia neta.
                    Packaging no descuenta stock y los costos indirectos no
                    registran gastos.
                </p>
                <nav className="flex flex-wrap gap-4">
                    <Link
                        className="underline"
                        href="/admin/inventory/costing-policies"
                    >
                        Configurar políticas
                    </Link>
                    <Link className="underline" href="/admin/settings/products">
                        Administrar productos
                    </Link>
                    <Link className="underline" href="/admin/inventory">
                        Inventario
                    </Link>
                </nav>
            </header>
            <div>
                <Label htmlFor="costing-product">Producto</Label>
                <select
                    id="costing-product"
                    className="w-full rounded-md border border-admin-border bg-admin-surface p-2"
                    value={selectedProductId ?? ''}
                    onChange={(event) =>
                        router.get(
                            '/admin/inventory/costing',
                            event.target.value
                                ? { product_id: event.target.value }
                                : {},
                        )
                    }
                >
                    <option value="">Seleccionar producto</option>
                    {products.map((p) => (
                        <option key={p.id} value={p.id}>
                            {p.name} ·{' '}
                            {p.pricing_family === 'empanada'
                                ? 'Empanada'
                                : 'Unidad'}
                        </option>
                    ))}
                </select>
            </div>
            {economics ? (
                <ProductEconomics
                    key={economics.product_id}
                    economics={economics}
                    policies={policies}
                />
            ) : (
                <p role="status">
                    {products.length
                        ? 'Selecciona un producto para ver su costo y la configuración pendiente.'
                        : 'No hay productos. Crea un producto desde la configuración del catálogo.'}
                </p>
            )}
            <p className="text-sm">
                Precios del backend POS-0. El margen estimado no es resultado
                financiero: no incorpora necesariamente impuestos, alquiler,
                salarios, desperdicio o comisiones. El costing de carritos
                combinados queda para un bloque posterior.
            </p>
        </section>
    );
}
