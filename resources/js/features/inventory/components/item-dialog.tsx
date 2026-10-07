import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { useForm } from '@inertiajs/react';
import { inventoryUnitName } from '../presentation';
import type {
    InventoryCategory,
    InventoryItem,
    InventoryUnit,
    UnitFamily,
} from '../types';
import { FormField } from './form-field';

export const itemKinds: Record<string, string> = {
    raw_material: 'Materia prima',
    semi_finished: 'Semielaborado',
    finished: 'Producto terminado',
    packaging: 'Packaging',
    consumable: 'Consumible',
    other: 'Otro',
};
const selectClass =
    'h-10 w-full rounded-md border border-input bg-background px-3 text-sm';

export function ItemDialog({
    item,
    categories,
    units,
    onClose,
}: {
    item?: InventoryItem;
    categories: InventoryCategory[];
    units: InventoryUnit[];
    onClose: () => void;
}) {
    const defaultUnit = units.find((unit) => unit.code === 'g');
    const form = useForm({
        category_id: String(
            item?.category_id ??
                categories.find((category) => category.is_active)?.id ??
                '',
        ),
        name: item?.name ?? '',
        tracks_stock: item?.tracks_stock ?? true,
        unit_family: (item?.unit_family ?? 'weight') as UnitFamily,
        base_unit_id: String(item?.base_unit_id ?? defaultUnit?.id ?? ''),
        default_input_unit_id: String(
            item?.default_input_unit_id ?? defaultUnit?.id ?? '',
        ),
        quantity_precision: item?.quantity_precision ?? 3,
        is_active: item?.is_active ?? true,
        kind: item?.kind ?? 'raw_material',
        recipe_input_enabled: item?.recipe_input_enabled ?? false,
        production_output_enabled: item?.production_output_enabled ?? false,
        order_consumption_enabled: item?.order_consumption_enabled ?? false,
        reference_mass_grams_per_unit:
            item?.reference_mass_grams_per_unit ?? '',
    });
    const compatible = units.filter(
        (unit) => unit.family === form.data.unit_family,
    );
    const capabilities = [
        ['tracks_stock', 'Controlar stock'],
        ['recipe_input_enabled', 'Usar en recetas'],
        ['production_output_enabled', 'Salida de producción'],
        ['order_consumption_enabled', 'Consumo al vender'],
        ['is_active', 'Insumo activo'],
    ] as const;

    return (
        <Dialog
            open
            onOpenChange={(open) => {
                if (!open) {
                    onClose();
                }
            }}
        >
            <DialogContent className="max-h-[85dvh] overflow-y-auto sm:max-w-xl">
                <DialogHeader>
                    <DialogTitle>
                        {item ? 'Editar insumo' : 'Nuevo insumo'}
                    </DialogTitle>
                    <DialogDescription>
                        Configurar un insumo no modifica su stock. La unidad
                        base conserva las cantidades físicas.
                    </DialogDescription>
                </DialogHeader>
                <form
                    className="grid gap-4"
                    onSubmit={(event) => {
                        event.preventDefault();
                        const options = {
                            onSuccess: onClose,
                            preserveScroll: true,
                        };

                        if (item) {
                            form.patch(
                                `/admin/inventory/items/${item.id}`,
                                options,
                            );
                        } else {
                            form.post('/admin/inventory/items', options);
                        }
                    }}
                >
                    <FormField
                        id="item-name"
                        label="Nombre"
                        error={form.errors.name}
                    >
                        <Input
                            id="item-name"
                            value={form.data.name}
                            onChange={(event) =>
                                form.setData('name', event.target.value)
                            }
                            required
                            autoFocus
                        />
                    </FormField>
                    <FormField
                        id="item-category"
                        label="Categoría"
                        error={form.errors.category_id}
                    >
                        <select
                            id="item-category"
                            className={selectClass}
                            value={form.data.category_id}
                            onChange={(event) =>
                                form.setData('category_id', event.target.value)
                            }
                            required
                        >
                            {categories
                                .filter(
                                    (category) =>
                                        category.is_active ||
                                        category.id === item?.category_id,
                                )
                                .map((category) => (
                                    <option
                                        key={category.id}
                                        value={category.id}
                                    >
                                        {category.name}
                                    </option>
                                ))}
                        </select>
                    </FormField>
                    <FormField
                        id="item-kind"
                        label="Clasificación"
                        error={form.errors.kind}
                    >
                        <select
                            id="item-kind"
                            className={selectClass}
                            value={form.data.kind}
                            onChange={(event) =>
                                form.setData('kind', event.target.value)
                            }
                        >
                            {Object.entries(itemKinds).map(([value, label]) => (
                                <option key={value} value={value}>
                                    {label}
                                </option>
                            ))}
                        </select>
                    </FormField>
                    <FormField
                        id="item-family"
                        label="Tipo de medida"
                        error={form.errors.unit_family}
                    >
                        <select
                            id="item-family"
                            className={selectClass}
                            value={form.data.unit_family}
                            onChange={(event) => {
                                const family = event.target.value as UnitFamily;
                                const base = units.find(
                                    (unit) =>
                                        unit.family === family && unit.is_base,
                                );
                                form.setData({
                                    ...form.data,
                                    unit_family: family,
                                    base_unit_id: String(base?.id ?? ''),
                                    default_input_unit_id: String(
                                        base?.id ?? '',
                                    ),
                                    quantity_precision:
                                        family === 'count' ? 0 : 3,
                                    reference_mass_grams_per_unit: '',
                                });
                            }}
                        >
                            <option value="weight">Peso</option>
                            <option value="volume">Volumen</option>
                            <option value="count">Unidades</option>
                        </select>
                    </FormField>
                    <div className="grid gap-4 sm:grid-cols-2">
                        <FormField
                            id="item-base"
                            label="Unidad base"
                            error={form.errors.base_unit_id}
                        >
                            <select
                                id="item-base"
                                className={selectClass}
                                value={form.data.base_unit_id}
                                onChange={(event) =>
                                    form.setData(
                                        'base_unit_id',
                                        event.target.value,
                                    )
                                }
                            >
                                {compatible
                                    .filter((unit) => unit.is_base)
                                    .map((unit) => (
                                        <option key={unit.id} value={unit.id}>
                                            {inventoryUnitName(unit)}
                                        </option>
                                    ))}
                            </select>
                        </FormField>
                        <FormField
                            id="item-input"
                            label="Unidad de ingreso"
                            error={form.errors.default_input_unit_id}
                        >
                            <select
                                id="item-input"
                                className={selectClass}
                                value={form.data.default_input_unit_id}
                                onChange={(event) =>
                                    form.setData(
                                        'default_input_unit_id',
                                        event.target.value,
                                    )
                                }
                            >
                                {compatible.map((unit) => (
                                    <option key={unit.id} value={unit.id}>
                                        {inventoryUnitName(unit)}
                                    </option>
                                ))}
                            </select>
                        </FormField>
                    </div>
                    <FormField
                        id="item-precision"
                        label="Decimales admitidos en unidad base"
                        error={form.errors.quantity_precision}
                    >
                        <Input
                            id="item-precision"
                            type="number"
                            min="0"
                            max="6"
                            value={form.data.quantity_precision}
                            onChange={(event) =>
                                form.setData(
                                    'quantity_precision',
                                    Number(event.target.value),
                                )
                            }
                            required
                        />
                    </FormField>
                    {form.data.unit_family === 'count' && (
                        <FormField
                            id="item-mass"
                            label="Peso de referencia por unidad (g)"
                            error={form.errors.reference_mass_grams_per_unit}
                            hint="Opcional. Es una referencia; no cambia las unidades de stock."
                        >
                            <Input
                                id="item-mass"
                                inputMode="decimal"
                                value={form.data.reference_mass_grams_per_unit}
                                onChange={(event) =>
                                    form.setData(
                                        'reference_mass_grams_per_unit',
                                        event.target.value,
                                    )
                                }
                            />
                        </FormField>
                    )}
                    <fieldset className="grid gap-3">
                        <legend className="mb-2 text-sm font-medium">
                            Capacidades explícitas
                        </legend>
                        {capabilities.map(([key, label]) => (
                            <label
                                key={key}
                                className="flex items-center gap-2"
                            >
                                <Checkbox
                                    checked={form.data[key]}
                                    onCheckedChange={(value) =>
                                        form.setData(key, value === true)
                                    }
                                />
                                {label}
                            </label>
                        ))}
                    </fieldset>
                    {Object.entries(form.errors)
                        .filter(([key]) => key === 'measurement')
                        .map(([key, error]) => (
                            <p
                                key={key}
                                role="alert"
                                className="text-sm text-destructive"
                            >
                                {error}
                            </p>
                        ))}
                    <Button disabled={form.processing}>Guardar insumo</Button>
                </form>
            </DialogContent>
        </Dialog>
    );
}
