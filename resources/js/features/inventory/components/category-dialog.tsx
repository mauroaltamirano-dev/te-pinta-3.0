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
import type { InventoryCategory } from '../types';
import { FormField } from './form-field';

export function CategoryDialog({
    category,
    onClose,
}: {
    category?: InventoryCategory;
    onClose: () => void;
}) {
    const form = useForm({
        name: category?.name ?? '',
        is_active: category?.is_active ?? true,
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
                    <DialogTitle>
                        {category ? 'Editar categoría' : 'Nueva categoría'}
                    </DialogTitle>
                    <DialogDescription>
                        Las categorías organizan los insumos; no determinan sus
                        capacidades.
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

                        if (category) {
                            form.patch(
                                `/admin/inventory/categories/${category.id}`,
                                options,
                            );
                        } else {
                            form.post('/admin/inventory/categories', options);
                        }
                    }}
                >
                    <FormField
                        id="category-name"
                        label="Nombre"
                        error={form.errors.name}
                    >
                        <Input
                            id="category-name"
                            value={form.data.name}
                            onChange={(event) =>
                                form.setData('name', event.target.value)
                            }
                            required
                            autoFocus
                        />
                    </FormField>
                    <label className="flex items-center gap-2">
                        <Checkbox
                            checked={form.data.is_active}
                            onCheckedChange={(value) =>
                                form.setData('is_active', value === true)
                            }
                        />
                        Categoría activa
                    </label>
                    <Button disabled={form.processing}>
                        Guardar categoría
                    </Button>
                </form>
            </DialogContent>
        </Dialog>
    );
}
