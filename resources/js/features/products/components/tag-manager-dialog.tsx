import { router, useForm } from '@inertiajs/react';
import { LoaderCircle, Tags, Trash2 } from 'lucide-react';
import type { FormEvent } from 'react';
import { toast } from 'sonner';
import { Button } from '@/components/ui/button';
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
import type { ProductTag } from '../types';

interface TagManagerDialogProps {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    tags: ProductTag[];
}

export function TagManagerDialog({
    open,
    onOpenChange,
    tags,
}: TagManagerDialogProps) {
    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="admin-shell products-workspace max-h-[calc(100dvh-2rem)] min-w-0 overflow-y-auto p-4 text-foreground sm:max-w-2xl sm:p-6">
                <DialogHeader>
                    <DialogTitle className="flex items-center gap-2">
                        <Tags /> Gestionar etiquetas
                    </DialogTitle>
                    <DialogDescription>
                        Las etiquetas se muestran en el catálogo público. Para
                        eliminar una, primero quitála de todos los productos.
                    </DialogDescription>
                </DialogHeader>
                <div className="grid gap-3">
                    {tags.map((tag) => (
                        <TagRow key={tag.id} tag={tag} />
                    ))}
                    {tags.length === 0 && (
                        <p className="text-sm text-muted-foreground">
                            Aún no hay etiquetas.
                        </p>
                    )}
                </div>
                <Separator />
                <CreateTagForm />
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

function TagRow({ tag }: { tag: ProductTag }) {
    const form = useForm({ name: tag.name, slug: tag.slug });
    const submit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        form.put(`/admin/settings/tags/${tag.id}`, {
            preserveScroll: true,
            onSuccess: () => toast.success('Etiqueta actualizada.'),
            onError: () => toast.error('No se pudo actualizar la etiqueta.'),
        });
    };

    return (
        <form
            onSubmit={submit}
            className="grid min-w-0 gap-3 rounded-lg border border-border/80 bg-card/45 p-3 sm:grid-cols-[minmax(0,1fr)_minmax(0,1fr)_auto_auto] sm:items-end"
        >
            <div className="grid min-w-0 gap-1">
                <Label htmlFor={`tag-name-${tag.id}`}>Nombre</Label>
                <Input
                    id={`tag-name-${tag.id}`}
                    value={form.data.name}
                    onChange={(event) =>
                        form.setData('name', event.target.value)
                    }
                    maxLength={100}
                    required
                    aria-invalid={Boolean(form.errors.name)}
                />
                {form.errors.name && (
                    <p role="alert" className="text-xs text-destructive">
                        {form.errors.name}
                    </p>
                )}
            </div>
            <div className="grid min-w-0 gap-1">
                <Label htmlFor={`tag-slug-${tag.id}`}>Identificador</Label>
                <Input
                    id={`tag-slug-${tag.id}`}
                    value={form.data.slug}
                    onChange={(event) =>
                        form.setData('slug', event.target.value)
                    }
                    maxLength={255}
                    required
                    aria-invalid={Boolean(form.errors.slug)}
                />
                {form.errors.slug && (
                    <p role="alert" className="text-xs text-destructive">
                        {form.errors.slug}
                    </p>
                )}
            </div>
            <Button
                type="submit"
                disabled={form.processing}
                className="min-h-11 sm:min-h-9"
            >
                {form.processing && <LoaderCircle className="animate-spin" />}{' '}
                Guardar
            </Button>
            <Button
                type="button"
                variant="outline"
                aria-label={`Eliminar etiqueta ${tag.name}`}
                onClick={() => {
                    if (window.confirm(`¿Eliminar la etiqueta ${tag.name}?`)) {
                        router.delete(`/admin/settings/tags/${tag.id}`, {
                            preserveScroll: true,
                            onSuccess: () =>
                                toast.success('Etiqueta eliminada.'),
                            onError: () =>
                                toast.error(
                                    'No se pudo eliminar la etiqueta. Quitála de los productos antes de reintentar.',
                                ),
                        });
                    }
                }}
                className="min-h-11 sm:min-h-9"
            >
                <Trash2 />
            </Button>
        </form>
    );
}

function CreateTagForm() {
    const form = useForm({ name: '', slug: '' });
    const submit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        form.post('/admin/settings/tags', {
            preserveScroll: true,
            onSuccess: () => {
                toast.success('Etiqueta creada.');
                form.reset();
            },
            onError: () => toast.error('No se pudo crear la etiqueta.'),
        });
    };

    return (
        <form
            onSubmit={submit}
            className="grid min-w-0 gap-3 sm:grid-cols-[minmax(0,1fr)_minmax(0,1fr)_auto] sm:items-end"
        >
            <div className="grid min-w-0 gap-1">
                <Label htmlFor="new-tag-name">Nueva etiqueta</Label>
                <Input
                    id="new-tag-name"
                    value={form.data.name}
                    onChange={(event) =>
                        form.setData('name', event.target.value)
                    }
                    maxLength={100}
                    required
                    aria-invalid={Boolean(form.errors.name)}
                />
                {form.errors.name && (
                    <p role="alert" className="text-xs text-destructive">
                        {form.errors.name}
                    </p>
                )}
            </div>
            <div className="grid min-w-0 gap-1">
                <Label htmlFor="new-tag-slug">Identificador (opcional)</Label>
                <Input
                    id="new-tag-slug"
                    value={form.data.slug}
                    onChange={(event) =>
                        form.setData('slug', event.target.value)
                    }
                    placeholder="Se genera desde el nombre"
                    maxLength={255}
                    aria-invalid={Boolean(form.errors.slug)}
                />
                {form.errors.slug && (
                    <p role="alert" className="text-xs text-destructive">
                        {form.errors.slug}
                    </p>
                )}
            </div>
            <Button
                type="submit"
                disabled={form.processing}
                className="min-h-11 sm:min-h-9"
            >
                {form.processing && <LoaderCircle className="animate-spin" />}{' '}
                Agregar
            </Button>
        </form>
    );
}
