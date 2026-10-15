import { Head, Link } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { formatInventoryQuantity } from '@/features/inventory/presentation';
import { PageLinks } from '@/features/inventory/recipes/page-links';
import {
    costCoverageLabels,
    formatRecipeMoney as money,
} from '@/features/inventory/recipes/presentation';
import type { ProductionBatch } from '@/features/inventory/recipes/types';
import type { Pagination } from '@/features/inventory/types';
export default function Production({
    batches,
}: {
    batches: Pagination<ProductionBatch>;
}) {
    return (
        <section className="grid gap-6 text-admin-text-primary">
            <Head title="Producción" />
            <Link href="/admin/inventory">Volver a Inventario</Link>
            <header className="flex flex-wrap items-center justify-between gap-3">
                <h1 className="text-2xl font-semibold">Producción</h1>
                <Button asChild>
                    <Link href="/admin/inventory/production/create">
                        Registrar producción
                    </Link>
                </Button>
            </header>
            {!batches.data.length && (
                <p role="status">
                    No hay producción registrada. El primer paso es{' '}
                    <Link className="underline" href="/admin/inventory/recipes">
                        crear una receta vigente
                    </Link>
                    ; luego se puede registrar la cantidad real producida.
                </p>
            )}
            {batches.data.map((b) => (
                <article
                    key={b.id}
                    className="grid gap-2 rounded-xl border border-admin-border p-4"
                >
                    <Link
                        className="font-semibold underline"
                        href={`/admin/inventory/production/${b.id}`}
                    >
                        {b.recipe_version.recipe_name_snapshot} · Lote #{b.id}
                    </Link>
                    <p>
                        {b.effective_date.slice(0, 10)} · Producido:{' '}
                        {formatInventoryQuantity(
                            b.actual_output_base_quantity,
                            b.output_item.base_unit,
                        )}
                    </p>
                    <p>
                        Costo histórico: {money(b.total_cost)} ·{' '}
                        {costCoverageLabels[b.cost_coverage]}
                        {b.reversal && ' · Revertido'}
                    </p>
                </article>
            ))}
            <PageLinks
                path="/admin/inventory/production"
                current={batches.current_page}
                last={batches.last_page}
            />
        </section>
    );
}
