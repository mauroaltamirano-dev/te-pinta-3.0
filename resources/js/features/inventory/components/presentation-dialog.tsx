import { useForm } from '@inertiajs/react';
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
import { inventoryUnitName } from '../presentation';
import type {
    InventoryItem,
    InventoryUnit,
    PurchasePresentation,
} from '../types';
import { FormField } from './form-field';

export function PresentationDialog({
    item,
    presentation,
    units,
    onClose,
}: {
    item: InventoryItem;
    presentation?: PurchasePresentation;
    units: InventoryUnit[];
    onClose: () => void;
}) {
    const form = useForm({
        inventory_item_id: item.id,
        name: presentation?.name ?? '',
        content_quantity: presentation?.content_quantity ?? '',
        content_unit_id: String(
            presentation?.content_unit_id ?? item.default_input_unit_id,
        ),
        is_active: presentation?.is_active ?? true,
    });

    return (
        <Dialog
            open
            onOpenChange={(open) => {
                if (!open) {
                    onClose();
                }
            }}
        >
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Presentaciones: {item.name}</DialogTitle>
                    <DialogDescription>
                        Definí el contenido físico de cada envase de compra.
                        Guardar una presentación no registra una compra ni
                        modifica stock.
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

                        if (presentation) {
                            form.patch(
                                `/admin/inventory/presentations/${presentation.id}`,
                                options,
                            );
                        } else {
                            form.post(
                                '/admin/inventory/presentations',
                                options,
                            );
                        }
                    }}
                >
                    <FormField
                        id="presentation-name"
                        label="Nombre"
                        error={form.errors.name}
                    >
                        <Input
                            id="presentation-name"
                            value={form.data.name}
                            onChange={(event) =>
                                form.setData('name', event.target.value)
                            }
                            required
                            autoFocus
                        />
                    </FormField>
                    <FormField
                        id="presentation-content"
                        label="Contenido"
                        error={form.errors.content_quantity}
                    >
                        <Input
                            id="presentation-content"
                            inputMode="decimal"
                            value={form.data.content_quantity}
                            onChange={(event) =>
                                form.setData(
                                    'content_quantity',
                                    event.target.value,
                                )
                            }
                            required
                        />
                    </FormField>
                    <FormField
                        id="presentation-unit"
                        label="Unidad de contenido"
                        error={form.errors.content_unit_id}
                    >
                        <select
                            id="presentation-unit"
                            className="h-10 w-full rounded-md border border-input bg-background px-3 text-sm"
                            value={form.data.content_unit_id}
                            onChange={(event) =>
                                form.setData(
                                    'content_unit_id',
                                    event.target.value,
                                )
                            }
                        >
                            {units
                                .filter(
                                    (unit) => unit.family === item.unit_family,
                                )
                                .map((unit) => (
                                    <option key={unit.id} value={unit.id}>
                                        {inventoryUnitName(unit)}
                                    </option>
                                ))}
                        </select>
                    </FormField>
                    <label className="flex items-center gap-2">
                        <Checkbox
                            checked={form.data.is_active}
                            onCheckedChange={(value) =>
                                form.setData('is_active', value === true)
                            }
                        />
                        Presentación activa
                    </label>
                    <Button disabled={form.processing}>
                        Guardar presentación
                    </Button>
                </form>
            </DialogContent>
        </Dialog>
    );
}
