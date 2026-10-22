import { useForm } from '@inertiajs/react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { FormField } from '@/features/inventory/components/form-field';
import type { RecipeOptions, RecipeVersion } from './types';

const selectClass =
    'min-h-11 w-full min-w-0 rounded-md border border-admin-border bg-admin-canvas px-3 text-admin-text-primary';
export function RecipeForm({
    items,
    outputItems,
    units,
    path,
    initial,
    initialName = '',
    mode = 'version',
}: RecipeOptions & {
    path: string;
    initial?: RecipeVersion;
    initialName?: string;
    mode?: 'create' | 'version' | 'correct';
}) {
    const [search, setSearch] = useState('');
    const form = useForm({
        name: initialName,
        output_inventory_item_id: String(
            initial?.output_inventory_item_id ?? '',
        ),
        output_unit_id: String(initial?.output_unit_id ?? ''),
        expected_output_quantity: initial?.expected_output_quantity ?? '',
        ingredients: initial?.ingredients.map((i) => ({
            inventory_item_id: String(i.inventory_item_id),
            unit_id: String(i.unit_id),
            quantity: i.quantity,
        })) ?? [{ inventory_item_id: '', unit_id: '', quantity: '' }],
    });
    const output = outputItems.find(
        (i) => String(i.id) === form.data.output_inventory_item_id,
    );
    const errors = form.errors as Record<string, string>;
    const updateIngredient = (
        index: number,
        values: Partial<(typeof form.data.ingredients)[number]>,
    ) =>
        form.setData(
            'ingredients',
            form.data.ingredients.map((row, n) =>
                n === index ? { ...row, ...values } : row,
            ),
        );

    return (
        <form
            className="grid gap-5"
            onSubmit={(e) => {
                e.preventDefault();

                if (mode === 'correct') {
                    form.put(path);
                } else {
                    form.post(path);
                }
            }}
            onKeyDown={(e) => {
                // A stray Enter in a field must not save a sealed formula; only the button submits.
                if (
                    e.key === 'Enter' &&
                    e.target instanceof HTMLElement &&
                    ['INPUT', 'SELECT'].includes(e.target.tagName)
                ) {
                    e.preventDefault();
                }
            }}
        >
            {mode !== 'version' && (
                <FormField
                    id="recipe-name"
                    label="Nombre de la receta"
                    error={errors.name}
                >
                    <Input
                        id="recipe-name"
                        value={form.data.name}
                        onChange={(e) => form.setData('name', e.target.value)}
                        required
                        maxLength={160}
                    />
                </FormField>
            )}
            <div className="grid gap-4 sm:grid-cols-3">
                <FormField
                    id="recipe-output"
                    label="Item producido"
                    error={errors.output_inventory_item_id}
                >
                    <select
                        id="recipe-output"
                        className={selectClass}
                        value={form.data.output_inventory_item_id}
                        required
                        onChange={(e) => {
                            const i = outputItems.find(
                                (i) => String(i.id) === e.target.value,
                            );
                            form.setData({
                                ...form.data,
                                output_inventory_item_id: e.target.value,
                                output_unit_id: String(
                                    i?.default_input_unit_id ?? '',
                                ),
                            });
                        }}
                    >
                        <option value="">Seleccionar item</option>
                        {outputItems.map((i) => (
                            <option key={i.id} value={i.id}>
                                {i.name}
                            </option>
                        ))}
                    </select>
                </FormField>
                <FormField
                    id="recipe-expected"
                    label="Salida esperada"
                    error={errors.expected_output_quantity}
                    hint="Declaración explícita; no se suman los ingredientes."
                >
                    <Input
                        id="recipe-expected"
                        inputMode="decimal"
                        value={form.data.expected_output_quantity}
                        required
                        onChange={(e) =>
                            form.setData(
                                'expected_output_quantity',
                                e.target.value,
                            )
                        }
                    />
                </FormField>
                <FormField
                    id="recipe-output-unit"
                    label="Unidad de salida"
                    error={errors.output_unit_id}
                >
                    <select
                        id="recipe-output-unit"
                        className={selectClass}
                        required
                        value={form.data.output_unit_id}
                        onChange={(e) =>
                            form.setData('output_unit_id', e.target.value)
                        }
                    >
                        <option value="">Seleccionar unidad</option>
                        {units
                            .filter((u) => u.family === output?.unit_family)
                            .map((u) => (
                                <option key={u.id} value={u.id}>
                                    {u.code}
                                </option>
                            ))}
                    </select>
                </FormField>
            </div>
            <FormField
                id="ingredient-search"
                label="Buscar ingredientes"
                hint="Por nombre, categoría o clasificación."
            >
                <Input
                    id="ingredient-search"
                    type="search"
                    value={search}
                    onChange={(e) => setSearch(e.target.value)}
                />
            </FormField>
            <fieldset className="grid gap-4">
                <legend className="mb-3 font-semibold">
                    Ingredientes por lote
                </legend>
                {form.data.ingredients.map((row, index) => {
                    const item = items.find(
                        (i) => String(i.id) === row.inventory_item_id,
                    );
                    const visible = items.filter(
                        (i) =>
                            String(i.id) === row.inventory_item_id ||
                            `${i.name} ${i.category.name} ${i.kind ?? ''}`
                                .toLocaleLowerCase()
                                .includes(search.toLocaleLowerCase()),
                    );

                    return (
                        <div
                            key={index}
                            className="grid min-w-0 gap-3 rounded-lg border border-admin-border p-3 sm:grid-cols-[2fr_1fr_1fr_auto]"
                        >
                            <FormField
                                id={`ingredient-${index}`}
                                label={`Ingrediente ${index + 1}`}
                                error={
                                    errors[
                                        `ingredients.${index}.inventory_item_id`
                                    ]
                                }
                            >
                                <select
                                    id={`ingredient-${index}`}
                                    className={selectClass}
                                    value={row.inventory_item_id}
                                    required
                                    onChange={(e) => {
                                        const i = items.find(
                                            (i) =>
                                                String(i.id) === e.target.value,
                                        );
                                        updateIngredient(index, {
                                            inventory_item_id: e.target.value,
                                            unit_id: String(
                                                i?.default_input_unit_id ?? '',
                                            ),
                                        });
                                    }}
                                >
                                    <option value="">Seleccionar insumo</option>
                                    {visible.map((i) => (
                                        <option key={i.id} value={i.id}>
                                            {i.name} · {i.category.name}
                                        </option>
                                    ))}
                                </select>
                            </FormField>
                            <FormField
                                id={`ingredient-quantity-${index}`}
                                label={`Cantidad ${index + 1}`}
                                error={errors[`ingredients.${index}.quantity`]}
                            >
                                <Input
                                    id={`ingredient-quantity-${index}`}
                                    required
                                    inputMode="decimal"
                                    value={row.quantity}
                                    onChange={(e) =>
                                        updateIngredient(index, {
                                            quantity: e.target.value,
                                        })
                                    }
                                />
                            </FormField>
                            <FormField
                                id={`ingredient-unit-${index}`}
                                label={`Unidad ${index + 1}`}
                                error={errors[`ingredients.${index}.unit_id`]}
                            >
                                <select
                                    id={`ingredient-unit-${index}`}
                                    className={selectClass}
                                    value={row.unit_id}
                                    required
                                    onChange={(e) =>
                                        updateIngredient(index, {
                                            unit_id: e.target.value,
                                        })
                                    }
                                >
                                    <option value="">Seleccionar unidad</option>
                                    {units
                                        .filter(
                                            (u) =>
                                                u.family === item?.unit_family,
                                        )
                                        .map((u) => (
                                            <option key={u.id} value={u.id}>
                                                {u.code}
                                            </option>
                                        ))}
                                </select>
                            </FormField>
                            <Button
                                className="self-end"
                                type="button"
                                variant="outline"
                                aria-label={`Quitar ingrediente ${index + 1}`}
                                disabled={form.data.ingredients.length === 1}
                                onClick={() =>
                                    form.setData(
                                        'ingredients',
                                        form.data.ingredients.filter(
                                            (_, n) => n !== index,
                                        ),
                                    )
                                }
                            >
                                Quitar
                            </Button>
                        </div>
                    );
                })}
                <Button
                    type="button"
                    variant="outline"
                    onClick={() =>
                        form.setData('ingredients', [
                            ...form.data.ingredients,
                            {
                                inventory_item_id: '',
                                unit_id: '',
                                quantity: '',
                            },
                        ])
                    }
                >
                    Agregar ingrediente
                </Button>
            </fieldset>
            {Object.keys(errors).length > 0 && (
                <div role="alert">
                    <p>No se pudo guardar la receta:</p>
                    <ul className="list-inside list-disc">
                        {Object.entries(errors).map(([key, value]) => (
                            <li key={key}>{value}</li>
                        ))}
                    </ul>
                </div>
            )}
            <p className="text-sm text-admin-text-secondary">
                {mode === 'correct'
                    ? 'Esta versión todavía no se usó en producción: la corrección la reemplaza sin dejar historial.'
                    : 'Una vez usada en producción, la fórmula no se puede corregir: los cambios crean una nueva versión y retiran la anterior.'}
            </p>
            <Button
                type="submit"
                disabled={
                    form.processing ||
                    outputItems.length === 0 ||
                    items.length === 0
                }
            >
                {form.processing
                    ? 'Guardando…'
                    : {
                          create: 'Crear receta',
                          version: 'Guardar nueva versión',
                          correct: 'Guardar corrección',
                      }[mode]}
            </Button>
        </form>
    );
}
