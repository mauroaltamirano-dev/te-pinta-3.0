import { AdjustmentForm } from '@/features/inventory/adjustments/form';
import { adjustmentTypes } from '@/features/inventory/adjustments/presentation';
import type {
    Adjustment,
    AdjustmentItem,
} from '@/features/inventory/adjustments/types';
import { formatInventoryQuantity } from '@/features/inventory/presentation';
import { PageLinks } from '@/features/inventory/recipes/page-links';
import {
    formatRecipeMoney as money,
    costCoverageLabels,
} from '@/features/inventory/recipes/presentation';
import type { InventoryUnit, Pagination } from '@/features/inventory/types';
import { Head, Link } from '@inertiajs/react';
export default function Adjustments({
    items,
    units,
    business_today,
    adjustments,
}: {
    items: AdjustmentItem[];
    units: InventoryUnit[];
    business_today: string;
    adjustments: Pagination<Adjustment>;
}) {
    return (
        <section className="grid gap-6 text-admin-text-primary">
            <Head title="Ajustes de inventario" />
            <Link href="/admin/inventory">Volver a Inventario</Link>
            <h1 className="text-2xl font-semibold">Ajustes de inventario</h1>
            <AdjustmentForm
                items={items}
                units={units}
                today={business_today}
            />
            <h2 className="text-xl font-semibold">Historial de ajustes</h2>
            {!adjustments.data.length && (
                <p>
                    No hay ajustes registrados. Seleccionar un item y un tipo,
                    revisar la vista previa y confirmar.
                </p>
            )}
            {adjustments.data.map((a) => (
                <article className="grid gap-2 rounded border p-4" key={a.id}>
                    <Link
                        className="font-semibold underline"
                        href={`/admin/inventory/adjustments/${a.id}`}
                    >
                        {adjustmentTypes[a.type]} · {a.item.name} · #{a.id}
                    </Link>
                    <p>
                        {a.effective_date.slice(0, 10)} ·{' '}
                        {formatInventoryQuantity(a.delta, a.item.base_unit)}
                    </p>
                    <p>
                        Costo histórico: {money(a.cost_total)} ·{' '}
                        {costCoverageLabels[a.cost_coverage]}
                        {a.reversal && ' · Revertido'}
                    </p>
                </article>
            ))}
            <PageLinks
                path="/admin/inventory/adjustments"
                current={adjustments.current_page}
                last={adjustments.last_page}
            />
        </section>
    );
}
