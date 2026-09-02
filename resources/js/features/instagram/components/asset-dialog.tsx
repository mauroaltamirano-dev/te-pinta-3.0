import { useForm } from '@inertiajs/react';
import { ImagePlus, LoaderCircle, TriangleAlert, Upload } from 'lucide-react';
import type { FormEvent, ReactNode } from 'react';
import { useEffect, useRef, useState } from 'react';
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
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { assetCategories, isStoryRatio } from '../presentation';
import { instagramRoutes } from '../routes';
import type { InstagramStoryAsset } from '../types';
import { InstagramSwitch } from './instagram-switch';

interface AssetFormData {
    name: string;
    category: string;
    image: File | null;
    is_active: boolean;
}

export function AssetDialog({
    open,
    asset,
    onOpenChange,
}: {
    open: boolean;
    asset: InstagramStoryAsset | null;
    onOpenChange: (open: boolean) => void;
}) {
    const form = useForm<AssetFormData>({
        name: asset?.name ?? '',
        category: asset?.category ?? 'other',
        image: null,
        is_active: asset?.is_active ?? true,
    });
    const inputRef = useRef<HTMLInputElement>(null);
    const [preview, setPreview] = useState(asset?.preview_url ?? '');
    const [dimensions, setDimensions] = useState(
        asset ? { width: asset.width, height: asset.height } : null,
    );

    useEffect(() => {
        return () => {
            if (preview.startsWith('blob:')) {
                URL.revokeObjectURL(preview);
            }
        };
    }, [preview]);

    const chooseImage = (file: File | null) => {
        if (!file) {
            return;
        }

        const objectUrl = URL.createObjectURL(file);
        const image = new Image();
        image.onload = () =>
            setDimensions({
                width: image.naturalWidth,
                height: image.naturalHeight,
            });
        image.src = objectUrl;
        form.setData('image', file);
        setPreview(objectUrl);
    };
    const submit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        const options = {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => onOpenChange(false),
        };

        if (asset) {
            form.transform((data) => ({ ...data, _method: 'patch' }));
            form.post(instagramRoutes.asset(asset.id), options);

            return;
        }

        form.post(instagramRoutes.assets, options);
    };
    const ratioWarning =
        dimensions && !isStoryRatio(dimensions.width, dimensions.height);

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="admin-shell dark max-h-[calc(100vh-2rem)] overflow-y-auto border-admin-border bg-admin-surface sm:max-w-2xl">
                <DialogHeader>
                    <DialogTitle>
                        {asset ? 'Editar historia' : 'Subir historia'}
                    </DialogTitle>
                    <DialogDescription>
                        JPG o PNG. Recomendado: 1080 × 1920 px en formato 9:16.
                    </DialogDescription>
                </DialogHeader>
                <form
                    onSubmit={submit}
                    className="grid gap-5 sm:grid-cols-[12rem_1fr]"
                >
                    <div className="grid content-start gap-3">
                        <button
                            type="button"
                            aria-label={
                                preview
                                    ? 'Reemplazar imagen de la historia'
                                    : 'Seleccionar imagen para la historia'
                            }
                            onClick={() => inputRef.current?.click()}
                            className="group relative flex aspect-[9/16] w-full items-center justify-center overflow-hidden rounded-xl border border-dashed border-admin-border bg-admin-surface-raised focus-visible:ring-2 focus-visible:ring-admin-accent focus-visible:outline-none"
                        >
                            {preview ? (
                                <>
                                    <img
                                        src={preview}
                                        alt="Vista previa de la historia"
                                        className="size-full object-cover"
                                    />
                                    <span className="absolute inset-x-2 bottom-2 flex min-h-9 items-center justify-center gap-2 rounded-lg bg-black/70 px-2 text-xs font-semibold text-white opacity-0 transition-opacity group-hover:opacity-100 group-focus-visible:opacity-100">
                                        <Upload className="size-4" /> Reemplazar
                                    </span>
                                </>
                            ) : (
                                <span className="grid justify-items-center gap-2 px-4 text-center text-sm text-admin-text-secondary">
                                    <ImagePlus className="size-8 text-admin-accent" />
                                    Seleccionar imagen
                                </span>
                            )}
                        </button>
                        <Input
                            id="story-image"
                            ref={inputRef}
                            type="file"
                            accept="image/jpeg,image/png"
                            required={!asset}
                            className="sr-only"
                            onChange={(event) =>
                                chooseImage(event.target.files?.[0] ?? null)
                            }
                        />
                        {dimensions && (
                            <p className="text-center text-xs text-admin-text-tertiary tabular-nums">
                                {dimensions.width} × {dimensions.height} px
                            </p>
                        )}
                    </div>

                    <div className="grid content-start gap-5">
                        <Field
                            label="Nombre"
                            htmlFor="asset-name"
                            error={form.errors.name}
                        >
                            <Input
                                id="asset-name"
                                value={form.data.name}
                                onChange={(event) =>
                                    form.setData('name', event.target.value)
                                }
                                required
                                autoFocus
                                aria-invalid={Boolean(form.errors.name)}
                                placeholder="Ej. Ya estamos tomando pedidos"
                            />
                        </Field>
                        <Field
                            label="Categoría"
                            htmlFor="asset-category"
                            error={form.errors.category}
                        >
                            <Select
                                value={form.data.category}
                                onValueChange={(value) =>
                                    form.setData('category', value)
                                }
                            >
                                <SelectTrigger
                                    id="asset-category"
                                    className="w-full"
                                >
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    {assetCategories.map((category) => (
                                        <SelectItem
                                            key={category.value}
                                            value={category.value}
                                        >
                                            {category.label}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </Field>

                        {ratioWarning && (
                            <div className="flex gap-3 rounded-xl border border-amber-400/20 bg-amber-400/10 p-3 text-sm text-amber-100">
                                <TriangleAlert className="mt-0.5 size-4 shrink-0" />
                                <p>
                                    La imagen no tiene una proporción cercana a
                                    9:16. Puede recortarse al publicarse en
                                    historias.
                                </p>
                            </div>
                        )}
                        {form.errors.image && (
                            <p className="text-sm text-destructive">
                                {form.errors.image}
                            </p>
                        )}

                        <div className="flex min-h-16 items-center justify-between gap-4 rounded-xl border border-admin-border bg-admin-surface-raised px-4">
                            <div>
                                <p className="text-sm font-semibold text-white">
                                    Disponible para publicar
                                </p>
                                <p className="mt-0.5 text-xs text-admin-text-tertiary">
                                    Las imágenes inactivas no aparecen al
                                    programar.
                                </p>
                            </div>
                            <InstagramSwitch
                                checked={form.data.is_active}
                                label="Activar historia"
                                onCheckedChange={(checked) =>
                                    form.setData('is_active', checked)
                                }
                            />
                        </div>

                        <DialogFooter className="mt-1">
                            <Button
                                type="button"
                                variant="outline"
                                onClick={() => onOpenChange(false)}
                            >
                                Cancelar
                            </Button>
                            <Button type="submit" disabled={form.processing}>
                                {form.processing && (
                                    <LoaderCircle className="animate-spin" />
                                )}
                                {asset ? 'Guardar cambios' : 'Subir historia'}
                            </Button>
                        </DialogFooter>
                    </div>
                </form>
            </DialogContent>
        </Dialog>
    );
}

function Field({
    label,
    htmlFor,
    error,
    children,
}: {
    label: string;
    htmlFor: string;
    error?: string;
    children: ReactNode;
}) {
    return (
        <div className="grid gap-2">
            <Label htmlFor={htmlFor}>{label}</Label>
            {children}
            {error && <p className="text-sm text-destructive">{error}</p>}
        </div>
    );
}
