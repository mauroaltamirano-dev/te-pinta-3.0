import { Head, Link } from '@inertiajs/react';
import { RecipeForm } from '@/features/inventory/recipes/recipe-form';
import type { RecipeOptions } from '@/features/inventory/recipes/types';
export default function CreateRecipe(props: RecipeOptions) {
    return (
        <section className="grid gap-6 text-admin-text-primary">
            <Head title="Crear receta" />
            <Link href="/admin/inventory/recipes">Volver a Recetas</Link>
            <h1 className="text-2xl font-semibold">Crear receta</h1>
            {(!props.items.length || !props.outputItems.length) && (
                <p role="status">
                    Configurá insumos aptos para receta y un item apto para
                    producción en{' '}
                    <Link className="underline" href="/admin/inventory">
                        Inventario
                    </Link>
                    .
                </p>
            )}
            <RecipeForm
                {...props}
                path="/admin/inventory/recipes"
                mode="create"
            />
        </section>
    );
}
