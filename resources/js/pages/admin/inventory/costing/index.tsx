import { Head, Link, router, useForm } from '@inertiajs/react';
import { AdminPageShell } from '@/components/shared/admin-page-shell';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    percentage,
    presentationName,
} from '@/features/inventory/costing/types';
import type {
    CostLine,
    Economics,
    EconomicsPresentation,
} from '@/features/inventory/costing/types';
import { formatInventoryQuantity } from '@/features/inventory/presentation';
import { formatMoney } from '@/lib/format';

type PolicyOption = { id: number; name: string };
const selectClass =
    'w-full rounded-md border border-admin-border bg-admin-surface p-2';

/** Price, cost, profit and percentage of one presentation; null is "not known yet", never zero. */
function figures(p: EconomicsPresentation) {
    return {
        name: presentationName(p.pricing_mode, p.commercial_quantity),
        price:
            p.selling_price === null
                ? 'Sin precio'
                : formatMoney(p.selling_price),
        cost:
            p.estimated_total_cost === null
                ? 'Falta'
                : formatMoney(p.estimated_total_cost),
        profit:
            p.estimated_margin === null ? '—' : formatMoney(p.estimated_margin),
        percent:
            p.estimated_margin_percentage_bps === null
                ? '—'
                : percentage(p.estimated_margin_percentage_bps),
        loss: (p.estimated_margin ?? 0) < 0,
    };
}

function EconomicsTable({ economics: e }: { economics: Economics }) {
    const rows = e.presentations.map((p) => ({
        key: p.commercial_quantity,
        ...figures(p),
    }));
    const lossClass = (loss: boolean) => (loss ? 'text-destructive' : '');

    return (
        <>
            <table className="hidden w-full text-left sm:table">
                <caption className="sr-only">
                    Precio, costo y ganancia de {e.product_name}
                </caption>
                <thead>
                    <tr className="border-b border-admin-border text-sm text-admin-text-secondary">
                        <th scope="col" className="py-2 font-medium">
                            Venta
                        </th>
                        <th scope="col" className="py-2 text-right font-medium">
                            Precio
                        </th>
                        <th scope="col" className="py-2 text-right font-medium">
                            Costo
                        </th>
                        <th scope="col" className="py-2 text-right font-medium">
                            Ganancia
                        </th>
                        <th scope="col" className="py-2 text-right font-medium">
                            %
                        </th>
                    </tr>
                </thead>
                <tbody>
                    {rows.map((r) => (
                        <tr
                            key={r.key}
                            className="border-b border-admin-border last:border-0"
                            data-testid="costing-row"
                        >
                            <th scope="row" className="py-3 font-semibold">
                                {r.name}
                            </th>
                            <td className="py-3 text-right">{r.price}</td>
                            <td className="py-3 text-right">{r.cost}</td>
                            <td
                                className={`py-3 text-right ${lossClass(r.loss)}`}
                            >
                                {r.profit}
                            </td>
                            <td
                                className={`py-3 text-right ${lossClass(r.loss)}`}
                            >
                                {r.percent}
                            </td>
                        </tr>
                    ))}
                </tbody>
            </table>
            <ul className="grid gap-3 sm:hidden" aria-label={e.product_name}>
                {rows.map((r) => (
                    <li
                        key={r.key}
                        className="rounded-md border border-admin-border p-3"
                        data-testid="costing-card"
                    >
                        <h3 className="font-semibold">{r.name}</h3>
                        <dl className="mt-2 grid grid-cols-2 gap-x-4 gap-y-1">
                            {[
                                ['Precio', r.price, ''],
                                ['Costo', r.cost, ''],
                                ['Ganancia', r.profit, lossClass(r.loss)],
                                ['%', r.percent, lossClass(r.loss)],
                            ].map(([label, value, className]) => (
                                <div
                                    key={label}
                                    className="flex justify-between gap-2"
                                >
                                    <dt className="text-admin-text-secondary">
                                        {label}
                                    </dt>
                                    <dd className={className}>{value}</dd>
                                </div>
                            ))}
                        </dl>
                    </li>
                ))}
            </ul>
        </>
    );
}

function lineText(line: CostLine) {
    const cost =
        line.estimated_cost === null
            ? 'falta su costo'
            : formatMoney(line.estimated_cost);

    return `${line.item_name} · ${formatInventoryQuantity(line.quantity, line.unit_code)} · ${cost}${line.cost_source === 'recipe' ? ' (estimado por receta)' : ''}`;
}

function CostBreakdown({ economics: e }: { economics: Economics }) {
    const overhead = e.costing_policy?.overhead_basis_points ?? null;

    return (
        <details className="min-w-0">
            <summary className="cursor-pointer font-medium">
                Ver de qué se compone el costo
            </summary>
            <div className="mt-3 grid gap-4 lg:grid-cols-3">
                {e.presentations.map((p) => (
                    <section key={p.commercial_quantity} className="min-w-0">
                        <h3 className="font-semibold">
                            {presentationName(
                                p.pricing_mode,
                                p.commercial_quantity,
                            )}
                        </h3>
                        <ul className="mt-1 grid gap-1 text-sm break-words">
                            {p.direct_components.map((l) => (
                                <li key={`d${l.inventory_item_id}`}>
                                    {lineText(l)}
                                </li>
                            ))}
                            {p.packaging_components.map((l) => (
                                <li key={`p${l.inventory_item_id}`}>
                                    {lineText(l)}
                                </li>
                            ))}
                            {p.packaging_status === 'none' && (
                                <li>No lleva caja ni envoltorio</li>
                            )}
                            {overhead !== null && (
                                <li>
                                    Gastos generales ({percentage(overhead)})
                                    {p.overhead_estimate !== null &&
                                        ` · ${formatMoney(p.overhead_estimate)}`}
                                </li>
                            )}
                        </ul>
                    </section>
                ))}
            </div>
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
        <div className="grid gap-6">
            <Card className="admin-ui admin-panel">
                <CardContent className="grid gap-4">
                    <h2 className="text-xl font-semibold">{e.product_name}</h2>
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
                                    Calcular para (unidades)
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
                                Calcular
                            </Button>
                        </form>
                    )}
                    <EconomicsTable economics={e} />
                    {e.recipe_estimated_items.length > 0 && (
                        <p
                            className="text-sm"
                            data-testid="recipe-estimate-note"
                        >
                            Estimado por receta:{' '}
                            {e.recipe_estimated_items.join(', ')}. Todavía no
                            hay stock con costo conocido, así que se usa lo que
                            cuesta hoy hacer su receta.
                        </p>
                    )}
                    {e.next_steps.length > 0 && (
                        <section aria-labelledby="next-steps-title">
                            <h3 id="next-steps-title" className="font-semibold">
                                Para completar el costo
                            </h3>
                            <ul className="mt-2 grid gap-2">
                                {e.next_steps.map((step) => (
                                    <li key={step.text}>
                                        {step.href.startsWith('#') ? (
                                            <a
                                                className="underline"
                                                href={step.href}
                                            >
                                                {step.text}
                                            </a>
                                        ) : (
                                            <Link
                                                className="underline"
                                                href={step.href}
                                            >
                                                {step.text}
                                            </Link>
                                        )}
                                    </li>
                                ))}
                            </ul>
                        </section>
                    )}
                    <CostBreakdown economics={e} />
                </CardContent>
            </Card>
            <Card id="packaging-y-gastos" className="admin-ui admin-panel">
                <CardContent className="grid gap-3">
                    <h2 className="font-semibold">
                        Packaging y gastos generales
                    </h2>
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
                                ¿Qué packaging y gastos usa este producto?
                            </Label>
                            <select
                                id="costing-policy"
                                className={selectClass}
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
                                <option value="">Ninguno</option>
                                {e.policy_status === 'inactive' && (
                                    <option
                                        value={e.costing_policy?.id}
                                        disabled
                                    >
                                        {e.costing_policy?.name} (desactivado)
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
                            Guardar
                        </Button>
                        {form.errors.costing_policy_id && (
                            <p role="alert">{form.errors.costing_policy_id}</p>
                        )}
                    </form>
                    {e.costing_policy && (
                        <p className="text-sm">
                            Suma{' '}
                            {percentage(e.costing_policy.overhead_basis_points)}{' '}
                            de gastos generales sobre insumos y packaging.{' '}
                            <Link
                                className="underline"
                                href={`/admin/inventory/costing-policies?edit=${e.costing_policy.id}`}
                            >
                                Editar «{e.costing_policy.name}»
                            </Link>
                        </p>
                    )}
                    <Link
                        className="text-sm underline"
                        href={`/admin/inventory/product-consumption/${e.product_id}`}
                    >
                        Editar qué insumos lleva {e.product_name}
                    </Link>
                </CardContent>
            </Card>
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
        <AdminPageShell
            title="Costos y ganancias"
            description="Cuánto te cuesta hoy cada producto y cuánto ganás con su precio actual."
            actions={
                <Button asChild variant="outline">
                    <Link href="/admin/inventory/costing-policies">
                        Packaging y gastos
                    </Link>
                </Button>
            }
        >
            <Head title="Costos y ganancias" />
            <div className="admin-ui">
                <Label htmlFor="costing-product">Producto</Label>
                <select
                    id="costing-product"
                    className={selectClass}
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
                    <option value="">Elegí un producto</option>
                    {products.map((p) => (
                        <option key={p.id} value={p.id}>
                            {p.name}
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
                        ? 'Elegí un producto para ver cuánto cuesta y cuánto ganás.'
                        : 'No hay productos. Creá uno desde Productos.'}
                </p>
            )}
            <p className="text-sm text-admin-text-secondary">
                Es una estimación con los costos de hoy: no descuenta stock ni
                registra gastos.
            </p>
        </AdminPageShell>
    );
}
