import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
import {
    formatInventoryQuantity,
    inventoryUnitLabel,
} from '@/features/inventory/presentation';
import { PageLinks } from '@/features/inventory/recipes/page-links';
import { costCoverageLabels } from '@/features/inventory/recipes/presentation';
import { RecipeForm } from '@/features/inventory/recipes/recipe-form';
import type {
    Recipe,
    RecipeOptions,
    RecipePage,
} from '@/features/inventory/recipes/types';
import { formatCost as money } from '@/lib/format';
export default function ShowRecipe({
    recipe,
    versions,
    version_view,
    can_correct,
    needs_first_version,
    errors,
    ...options
}: RecipeOptions & {
    recipe: Recipe;
    versions: RecipePage;
    version_view: 'current' | 'retired';
    can_correct: boolean;
    needs_first_version: boolean;
    errors?: Record<string, string>;
}) {
    const [editing, setEditing] = useState<'version' | 'correct' | null>(null);
    const toggle = (mode: 'version' | 'correct') =>
        setEditing(editing === mode ? null : mode);
    const retire = () => {
        if (
            window.confirm(
                `¿Retirar la receta ${recipe.name}? No se podrá usar en producción ni tener versiones nuevas. Su historial seguirá visible en «Retiradas».`,
            )
        ) {
            router.post(`/admin/inventory/recipes/${recipe.id}/retire`);
        }
    };

    return (
        <section className="grid gap-6 text-admin-text-primary">
            <Head title={recipe.name} />
            <Link href="/admin/inventory/recipes">Volver a Recetas</Link>
            <h1 className="text-2xl font-semibold text-balance">
                {recipe.name}
            </h1>
            {recipe.is_active ? (
                <div>
                    <Button variant="outline" onClick={retire}>
                        Retirar receta
                    </Button>
                </div>
            ) : (
                <p role="status">
                    Receta retirada: no se puede usar en producción ni tener
                    versiones nuevas. Su historial sigue en «Retiradas».
                </p>
            )}
            {errors?.recipe && <p role="alert">{errors.recipe}</p>}
            <nav
                aria-label="Versiones de receta"
                className="flex flex-wrap gap-4"
            >
                {(['current', 'retired'] as const).map((view) => (
                    <Link
                        key={view}
                        className="inline-flex min-h-11 items-center underline"
                        aria-current={
                            version_view === view ? 'page' : undefined
                        }
                        href={`/admin/inventory/recipes/${recipe.id}?view=${view}`}
                    >
                        {view === 'current' ? 'Actual' : 'Retiradas'}
                    </Link>
                ))}
            </nav>
            {!versions.data.length && (
                <p role="status">
                    {version_view === 'current'
                        ? needs_first_version
                            ? 'Esta receta todavía no tiene fórmula. Cargá la primera versión para poder usarla en producción, o retirala si no la vas a usar.'
                            : 'No hay una versión vigente.'
                        : 'No hay versiones retiradas.'}
                </p>
            )}
            {needs_first_version && version_view === 'current' && (
                <RecipeForm
                    {...options}
                    mode="first"
                    path={`/admin/inventory/recipes/${recipe.id}/versions`}
                />
            )}
            {versions.data.map((v) => (
                <article
                    key={v.id}
                    className="grid min-w-0 gap-4 rounded-xl border border-admin-border bg-admin-surface p-4"
                >
                    <h2 className="text-xl font-semibold">
                        Versión {v.version} ·{' '}
                        {v.is_active ? 'Actual' : 'Retirada'}
                    </h2>
                    <p>Item producido: {v.output_item.name}</p>
                    <p>
                        Salida esperada:{' '}
                        {formatInventoryQuantity(
                            v.expected_output_quantity,
                            v.output_unit,
                        )}
                    </p>
                    <ul className="grid gap-2">
                        {v.ingredients.map((i) => (
                            <li key={i.id}>
                                {i.item.name}:{' '}
                                {formatInventoryQuantity(i.quantity, i.unit)} ·{' '}
                                {money(
                                    v.current_cost.ingredients.find(
                                        (line) =>
                                            line.recipe_ingredient_id === i.id,
                                    )?.estimated_cost ?? null,
                                )}
                                {i.reference_mass_grams && (
                                    <span>
                                        {' '}
                                        · Masa de referencia:{' '}
                                        {formatInventoryQuantity(
                                            i.reference_mass_grams,
                                            'g',
                                        )}{' '}
                                        (auxiliar; stock en unidades)
                                    </span>
                                )}
                            </li>
                        ))}
                    </ul>
                    {v.theoretical_mass && (
                        <p className="text-sm">
                            Masa de referencia comparable:{' '}
                            {formatInventoryQuantity(
                                v.theoretical_mass.grams,
                                'g',
                            )}{' '}
                            ({v.theoretical_mass.comparable_count}/
                            {v.theoretical_mass.total_count} ingredientes). No
                            define la salida esperada.
                        </p>
                    )}
                    <div>
                        <h3 className="font-semibold">Costo estimado actual</h3>
                        <p>
                            {money(v.current_cost.estimated_total_cost)} ·{' '}
                            {costCoverageLabels[v.current_cost.status]}
                        </p>
                        <p>
                            {money(
                                v.current_cost.estimated_cost_per_output_unit,
                            )}{' '}
                            / {inventoryUnitLabel('1', v.output_unit)}
                        </p>
                        <p>
                            Cobertura:{' '}
                            {v.current_cost.coverage.known_components}/
                            {v.current_cost.coverage.total_components}{' '}
                            ingredientes.
                        </p>
                        <p className="text-sm text-admin-text-secondary">
                            Precios actuales de esta fórmula, incluso si está
                            retirada. No es el costo histórico de una
                            producción.
                        </p>
                    </div>
                    {v.is_active && (
                        <div className="flex flex-wrap gap-3">
                            <Button asChild>
                                <Link
                                    href={`/admin/inventory/production/create?recipe=${recipe.id}`}
                                >
                                    Registrar producción
                                </Link>
                            </Button>
                            {can_correct && (
                                <Button
                                    variant="outline"
                                    onClick={() => toggle('correct')}
                                    aria-expanded={editing === 'correct'}
                                >
                                    Corregir receta
                                </Button>
                            )}
                            <Button
                                variant="outline"
                                onClick={() => toggle('version')}
                                aria-expanded={editing === 'version'}
                            >
                                Crear nueva versión
                            </Button>
                        </div>
                    )}
                    {v.is_active && editing !== null && (
                        <RecipeForm
                            key={`${v.id}-${editing}`}
                            {...options}
                            initial={v}
                            initialName={recipe.name}
                            mode={editing}
                            path={
                                editing === 'correct'
                                    ? `/admin/inventory/recipes/${recipe.id}/current-version`
                                    : `/admin/inventory/recipes/${recipe.id}/versions`
                            }
                        />
                    )}
                </article>
            ))}
            <PageLinks
                path={`/admin/inventory/recipes/${recipe.id}`}
                current={versions.current_page}
                last={versions.last_page}
                view={version_view}
            />
        </section>
    );
}
