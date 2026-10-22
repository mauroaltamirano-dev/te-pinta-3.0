import { Head, Link } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { PageLinks } from '@/features/inventory/recipes/page-links';
import type { Recipe } from '@/features/inventory/recipes/types';
import type { Pagination } from '@/features/inventory/types';
export default function Recipes({
    recipes,
    view,
}: {
    recipes: Pagination<Recipe>;
    view: 'active' | 'retired';
}) {
    return (
        <section className="grid gap-6 text-admin-text-primary">
            <Head title="Recetas" />
            <Link href="/admin/inventory">Volver a Inventario</Link>
            <header className="flex flex-wrap items-center justify-between gap-3">
                <h1 className="text-2xl font-semibold">Recetas</h1>
                <Button asChild>
                    <Link href="/admin/inventory/recipes/create">
                        Crear receta
                    </Link>
                </Button>
            </header>
            <nav
                aria-label="Estado de recetas"
                className="flex flex-wrap gap-4"
            >
                {(['active', 'retired'] as const).map((tab) => (
                    <Link
                        key={tab}
                        className="inline-flex min-h-11 items-center underline"
                        aria-current={view === tab ? 'page' : undefined}
                        href={`/admin/inventory/recipes?view=${tab}`}
                    >
                        {tab === 'active' ? 'Activas' : 'Retiradas'}
                    </Link>
                ))}
            </nav>
            {!recipes.data.length && (
                <p role="status">
                    {view === 'active'
                        ? 'Todavía no hay recetas activas. Para registrar producción, se necesita una fórmula con ingredientes y salida esperada.'
                        : 'No hay recetas retiradas.'}
                </p>
            )}
            {recipes.data.map((r) => (
                <article
                    key={r.id}
                    className="rounded-xl border border-admin-border p-4"
                >
                    <Link
                        className="text-lg font-semibold underline"
                        href={`/admin/inventory/recipes/${r.id}`}
                    >
                        {r.name}
                    </Link>
                    <p>
                        {r.versions_count}{' '}
                        {r.versions_count === 1 ? 'versión' : 'versiones'} ·{' '}
                        {r.is_active ? 'Activa' : 'Retirada'}
                    </p>
                </article>
            ))}
            <PageLinks
                path="/admin/inventory/recipes"
                current={recipes.current_page}
                last={recipes.last_page}
                view={view}
            />
        </section>
    );
}
