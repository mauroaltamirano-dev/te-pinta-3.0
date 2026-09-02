import { useForm } from '@inertiajs/react';
import { LoaderCircle, SlidersHorizontal } from 'lucide-react';
import type { FormEvent } from 'react';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Separator } from '@/components/ui/separator';
import type { ProductCategory } from '../types';

interface CategoryManagerDialogProps {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    categories: ProductCategory[];
}

export function CategoryManagerDialog({
    open,
    onOpenChange,
    categories,
}: CategoryManagerDialogProps) {
    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="dark admin-shell products-workspace max-h-[90vh] overflow-y-auto sm:max-w-2xl">
                <DialogHeader>
                    <DialogTitle className="flex items-center gap-2">
                        <SlidersHorizontal />
                        Gestionar categorías
                    </DialogTitle>
                    <DialogDescription>
                        Las categorías se ordenan manualmente. Las inactivas y
                        sus productos siguen disponibles en administración, pero
                        no aparecen en el menú público.
                    </DialogDescription>
                </DialogHeader>

                <div className="flex flex-col gap-3">
                    {categories.map((category) => (
                        <CategoryRow key={category.id} category={category} />
                    ))}
                </div>

                <Separator />
                <CreateCategoryForm />

                <DialogFooter>
                    <Button
                        type="button"
                        variant="outline"
                        onClick={() => onOpenChange(false)}
                    >
                        Cerrar
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}

function CategoryRow({ category }: { category: ProductCategory }) {
    const nameId = `category-name-${category.id}`;
    const orderId = `category-order-${category.id}`;
    const activeId = `category-active-${category.id}`;
    const form = useForm({
        name: category.name,
        sort_order: category.sort_order.toString(),
        is_active: category.is_active,
    });

    const submit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        form.put(`/admin/settings/categories/${category.id}`, {
            preserveScroll: true,
        });
    };

    return (
        <form
            onSubmit={submit}
            className="grid gap-3 rounded-lg border border-border/80 bg-card/45 p-4 sm:grid-cols-[1fr_7rem_auto_auto] sm:items-end"
        >
            <div className="flex flex-col gap-2">
                <Label htmlFor={nameId}>Nombre</Label>
                <Input
                    id={nameId}
                    value={form.data.name}
                    onChange={(event) =>
                        form.setData('name', event.target.value)
                    }
                    aria-invalid={Boolean(form.errors.name)}
                    aria-describedby={
                        form.errors.name ? `${nameId}-error` : undefined
                    }
                    required
                />
                <FieldError id={`${nameId}-error`} message={form.errors.name} />
            </div>
            <div className="flex flex-col gap-2">
                <Label htmlFor={orderId}>Orden</Label>
                <Input
                    id={orderId}
                    type="number"
                    min="0"
                    step="1"
                    value={form.data.sort_order}
                    onChange={(event) =>
                        form.setData('sort_order', event.target.value)
                    }
                    aria-invalid={Boolean(form.errors.sort_order)}
                    aria-describedby={
                        form.errors.sort_order ? `${orderId}-error` : undefined
                    }
                    required
                />
                <FieldError
                    id={`${orderId}-error`}
                    message={form.errors.sort_order}
                />
            </div>
            <div className="flex flex-col gap-1">
                <label className="flex h-9 items-center gap-2 text-sm">
                    <Checkbox
                        id={activeId}
                        checked={form.data.is_active}
                        onCheckedChange={(checked) =>
                            form.setData('is_active', checked === true)
                        }
                        aria-invalid={Boolean(form.errors.is_active)}
                        aria-describedby={
                            form.errors.is_active
                                ? `${activeId}-error`
                                : undefined
                        }
                        aria-required="true"
                    />
                    Activa
                </label>
                <FieldError
                    id={`${activeId}-error`}
                    message={form.errors.is_active}
                />
            </div>
            <Button
                type="submit"
                variant="secondary"
                disabled={form.processing}
            >
                {form.processing && (
                    <LoaderCircle
                        data-icon="inline-start"
                        className="animate-spin"
                    />
                )}
                Actualizar
            </Button>
        </form>
    );
}

function CreateCategoryForm() {
    const form = useForm({
        name: '',
        sort_order: '0',
        is_active: true,
    });

    const submit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        form.post('/admin/settings/categories', {
            preserveScroll: true,
            onSuccess: () => form.reset(),
        });
    };

    return (
        <form onSubmit={submit} className="flex flex-col gap-4">
            <div>
                <h3 className="text-sm font-semibold">Agregar categoría</h3>
                <p className="mt-1 text-xs text-muted-foreground">
                    El servidor genera los slugs de forma segura.
                </p>
            </div>
            <div className="grid gap-3 sm:grid-cols-[1fr_7rem_auto_auto] sm:items-end">
                <div className="flex flex-col gap-2">
                    <Label htmlFor="new-category-name">Nombre</Label>
                    <Input
                        id="new-category-name"
                        value={form.data.name}
                        onChange={(event) =>
                            form.setData('name', event.target.value)
                        }
                        aria-invalid={Boolean(form.errors.name)}
                        aria-describedby={
                            form.errors.name
                                ? 'new-category-name-error'
                                : undefined
                        }
                        required
                        placeholder="Nombre de la categoría"
                    />
                    <FieldError
                        id="new-category-name-error"
                        message={form.errors.name}
                    />
                </div>
                <div className="flex flex-col gap-2">
                    <Label htmlFor="new-category-order">Orden</Label>
                    <Input
                        id="new-category-order"
                        type="number"
                        min="0"
                        step="1"
                        value={form.data.sort_order}
                        onChange={(event) =>
                            form.setData('sort_order', event.target.value)
                        }
                        aria-invalid={Boolean(form.errors.sort_order)}
                        aria-describedby={
                            form.errors.sort_order
                                ? 'new-category-order-error'
                                : undefined
                        }
                        required
                    />
                    <FieldError
                        id="new-category-order-error"
                        message={form.errors.sort_order}
                    />
                </div>
                <div className="flex flex-col gap-1">
                    <label className="flex h-9 items-center gap-2 text-sm">
                        <Checkbox
                            id="new-category-active"
                            checked={form.data.is_active}
                            onCheckedChange={(checked) =>
                                form.setData('is_active', checked === true)
                            }
                            aria-invalid={Boolean(form.errors.is_active)}
                            aria-describedby={
                                form.errors.is_active
                                    ? 'new-category-active-error'
                                    : undefined
                            }
                            aria-required="true"
                        />
                        Activa
                    </label>
                    <FieldError
                        id="new-category-active-error"
                        message={form.errors.is_active}
                    />
                </div>
                <Button type="submit" disabled={form.processing}>
                    {form.processing && (
                        <LoaderCircle
                            data-icon="inline-start"
                            className="animate-spin"
                        />
                    )}
                    Agregar
                </Button>
            </div>
        </form>
    );
}

function FieldError({ id, message }: { id: string; message?: string }) {
    return message ? (
        <p id={id} role="alert" className="text-xs text-destructive">
            {message}
        </p>
    ) : null;
}
