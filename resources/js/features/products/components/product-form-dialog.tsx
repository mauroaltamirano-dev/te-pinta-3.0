import { router, useForm } from '@inertiajs/react';
import { ImageIcon, LoaderCircle, Trash2, Upload } from 'lucide-react';
import { useEffect, useMemo, useRef, useState } from 'react';
import type { FormEvent } from 'react';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
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
import {
    Select,
    SelectContent,
    SelectGroup,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import { cn } from '@/lib/utils';
import type { Product, ProductCategory, ProductTag } from '../types';
import { HomogeneousSurchargePreview } from './homogeneous-surcharge-preview';

interface ProductFormData {
    name: string;
    slug: string;
    description: string;
    long_description: string;
    public_ingredients: string;
    category_id: string;
    pricing_family: 'empanada' | 'unit';
    tags: number[];
    unit_price: string;
    half_dozen_price: string;
    homogeneous_half_dozen_surcharge: string;
    dozen_price: string;
    is_available: boolean;
    sort_order: string;
    image: File | null;
    remove_image: boolean;
}

interface ProductFormDialogProps {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    product: Product | null;
    categories: ProductCategory[];
    tags: ProductTag[];
    defaultCategoryId: number | null;
}

export function ProductFormDialog({
    open,
    onOpenChange,
    product,
    categories,
    tags,
    defaultCategoryId,
}: ProductFormDialogProps) {
    const form = useForm<ProductFormData>({
        name: product?.name ?? '',
        slug: product?.slug ?? '',
        description: product?.description ?? '',
        long_description: product?.long_description ?? '',
        public_ingredients: product?.public_ingredients ?? '',
        pricing_family: product?.pricing_family ?? 'empanada',
        category_id:
            product?.category_id.toString() ??
            defaultCategoryId?.toString() ??
            '',
        tags: product?.tags.map((tag) => tag.id) ?? [],
        unit_price: product?.unit_price.toString() ?? '',
        half_dozen_price: product?.half_dozen_price.toString() ?? '',
        homogeneous_half_dozen_surcharge:
            product?.homogeneous_half_dozen_surcharge ?? '2000',
        dozen_price: product?.dozen_price.toString() ?? '',
        is_available: product?.is_available ?? true,
        sort_order: product?.sort_order.toString() ?? '0',
        image: null,
        remove_image: false,
    });
    const [usingSurcharge, setUsingSurcharge] = useState(false);
    const [confirmingDelete, setConfirmingDelete] = useState(false);
    const imageInputRef = useRef<HTMLInputElement>(null);
    const removeButtonRef = useRef<HTMLButtonElement>(null);
    const deleteConfirmationRef = useRef<HTMLDivElement>(null);

    const previewUrl = useMemo(() => {
        if (form.data.image) {
            return URL.createObjectURL(form.data.image);
        }

        if (form.data.remove_image) {
            return null;
        }

        return product?.image_url ?? null;
    }, [form.data.image, form.data.remove_image, product?.image_url]);

    useEffect(() => {
        if (!form.data.image || !previewUrl) {
            return;
        }

        return () => URL.revokeObjectURL(previewUrl);
    }, [form.data.image, previewUrl]);

    const closeDialog = () => {
        setConfirmingDelete(false);
        onOpenChange(false);
    };

    const submit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();

        form.transform((data) => {
            const {
                half_dozen_price,
                dozen_price,
                homogeneous_half_dozen_surcharge,
                ...common
            } = data;
            const payload =
                data.pricing_family === 'unit'
                    ? common
                    : {
                          ...common,
                          dozen_price,
                          ...(usingSurcharge
                              ? { homogeneous_half_dozen_surcharge }
                              : { half_dozen_price }),
                      };

            return product ? { ...payload, _method: 'put' } : payload;
        });

        const options = {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: closeDialog,
        };

        if (product) {
            form.post(`/admin/settings/products/${product.id}`, options);

            return;
        }

        form.post('/admin/settings/products', options);
    };

    const deleteProduct = () => {
        if (!product) {
            return;
        }

        router.delete(`/admin/settings/products/${product.id}`, {
            preserveScroll: true,
            onSuccess: closeDialog,
        });
    };

    const toggleTag = (tagId: number, checked: boolean) => {
        form.setData(
            'tags',
            checked
                ? [...form.data.tags, tagId]
                : form.data.tags.filter((id) => id !== tagId),
        );
    };

    const tagError = Object.entries(form.errors).find(([key]) =>
        key.startsWith('tags'),
    )?.[1];

    const clearImage = () => {
        form.setData((data) => ({
            ...data,
            image: null,
            remove_image: Boolean(product?.image_url),
        }));

        if (imageInputRef.current) {
            imageInputRef.current.value = '';
        }
    };

    const restoreImage = () => {
        form.setData('remove_image', false);
    };

    const requestDelete = () => {
        setConfirmingDelete(true);
        requestAnimationFrame(() => deleteConfirmationRef.current?.focus());
    };

    const cancelDelete = () => {
        setConfirmingDelete(false);
        requestAnimationFrame(() => removeButtonRef.current?.focus());
    };

    return (
        <Dialog
            open={open}
            onOpenChange={(nextOpen) =>
                nextOpen ? onOpenChange(true) : closeDialog()
            }
        >
            <DialogContent className="admin-shell products-workspace dark top-[50dvh] flex h-[calc(100dvh-2rem)] max-h-[92vh] flex-col gap-0 overflow-hidden sm:top-1/2 sm:h-auto sm:max-w-3xl">
                <DialogHeader className="relative z-10 shrink-0">
                    <DialogTitle>
                        {product ? 'Editar producto' : 'Agregar producto'}
                    </DialogTitle>
                    <DialogDescription>
                        Los precios principales corresponden a productos
                        cocinados y se guardan en pesos. La configuración
                        general define los descuentos para pedidos crudos.
                    </DialogDescription>
                </DialogHeader>

                <form
                    id="product-form"
                    onSubmit={submit}
                    className="mt-4 flex min-h-0 flex-1 flex-col gap-6 overflow-y-auto pe-1"
                >
                    <div className="grid gap-6 md:grid-cols-[9rem_1fr]">
                        <div className="flex flex-col items-center gap-3">
                            <Avatar className="size-28 border-4 border-muted bg-muted">
                                {previewUrl && (
                                    <AvatarImage
                                        src={previewUrl}
                                        alt="Vista previa de la imagen del producto"
                                        className="object-cover"
                                    />
                                )}
                                <AvatarFallback>
                                    <ImageIcon className="size-8 text-muted-foreground" />
                                </AvatarFallback>
                            </Avatar>
                            <Label
                                htmlFor="product-image"
                                className="inline-flex h-9 cursor-pointer items-center justify-center gap-2 rounded-md border border-input bg-background px-3 text-sm font-medium shadow-xs hover:bg-accent"
                            >
                                <Upload />
                                Elegir imagen
                            </Label>
                            <Input
                                ref={imageInputRef}
                                id="product-image"
                                type="file"
                                accept="image/jpeg,image/png,image/webp"
                                className="sr-only"
                                aria-invalid={Boolean(form.errors.image)}
                                aria-describedby={
                                    form.errors.image
                                        ? 'product-image-error'
                                        : undefined
                                }
                                onChange={(event) => {
                                    form.setData((data) => ({
                                        ...data,
                                        image: event.target.files?.[0] ?? null,
                                        remove_image: false,
                                    }));
                                }}
                            />
                            {(previewUrl || form.data.remove_image) && (
                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="sm"
                                    onClick={
                                        form.data.remove_image
                                            ? restoreImage
                                            : clearImage
                                    }
                                >
                                    {form.data.remove_image
                                        ? 'Restaurar imagen actual'
                                        : 'Quitar imagen'}
                                </Button>
                            )}
                            <FieldError
                                id="product-image-error"
                                message={form.errors.image}
                            />
                        </div>

                        <div className="grid gap-4 sm:grid-cols-2">
                            <FormField
                                id="product-name"
                                label="Nombre"
                                error={form.errors.name}
                                className="sm:col-span-2"
                            >
                                <Input
                                    id="product-name"
                                    value={form.data.name}
                                    onChange={(event) =>
                                        form.setData('name', event.target.value)
                                    }
                                    aria-invalid={Boolean(form.errors.name)}
                                    aria-describedby={
                                        form.errors.name
                                            ? 'product-name-error'
                                            : undefined
                                    }
                                    required
                                    autoFocus
                                />
                            </FormField>

                            <FormField
                                id="product-slug"
                                label="URL pública"
                                error={form.errors.slug}
                                className="sm:col-span-2"
                            >
                                <Input
                                    id="product-slug"
                                    value={form.data.slug}
                                    onChange={(event) =>
                                        form.setData('slug', event.target.value)
                                    }
                                    placeholder="Se genera desde el nombre al crear"
                                    maxLength={255}
                                    aria-invalid={Boolean(form.errors.slug)}
                                    aria-describedby={
                                        form.errors.slug
                                            ? 'product-slug-error'
                                            : undefined
                                    }
                                />
                                <p className="mt-1 text-xs text-muted-foreground">
                                    Usá minúsculas, números y guiones. Cambiarla
                                    rompe los enlaces públicos anteriores.
                                </p>
                            </FormField>

                            <FormField
                                id="product-description"
                                label="Descripción breve"
                                error={form.errors.description}
                                className="sm:col-span-2"
                            >
                                <Textarea
                                    id="product-description"
                                    value={form.data.description}
                                    onChange={(event) =>
                                        form.setData(
                                            'description',
                                            event.target.value,
                                        )
                                    }
                                    aria-invalid={Boolean(
                                        form.errors.description,
                                    )}
                                    aria-describedby={
                                        form.errors.description
                                            ? 'product-description-error'
                                            : undefined
                                    }
                                    maxLength={500}
                                    rows={3}
                                />
                            </FormField>

                            <FormField
                                id="product-long-description"
                                label="Descripción completa para la web"
                                error={form.errors.long_description}
                                className="sm:col-span-2"
                            >
                                <Textarea
                                    id="product-long-description"
                                    value={form.data.long_description}
                                    onChange={(event) =>
                                        form.setData(
                                            'long_description',
                                            event.target.value,
                                        )
                                    }
                                    aria-invalid={Boolean(
                                        form.errors.long_description,
                                    )}
                                    aria-describedby={
                                        form.errors.long_description
                                            ? 'product-long-description-error'
                                            : undefined
                                    }
                                    maxLength={5000}
                                    rows={4}
                                />
                            </FormField>

                            <FormField
                                id="product-public-ingredients"
                                label="Ingredientes visibles en la web"
                                error={form.errors.public_ingredients}
                                className="sm:col-span-2"
                            >
                                <Textarea
                                    id="product-public-ingredients"
                                    value={form.data.public_ingredients}
                                    onChange={(event) =>
                                        form.setData(
                                            'public_ingredients',
                                            event.target.value,
                                        )
                                    }
                                    aria-invalid={Boolean(
                                        form.errors.public_ingredients,
                                    )}
                                    aria-describedby={
                                        form.errors.public_ingredients
                                            ? 'product-public-ingredients-error'
                                            : undefined
                                    }
                                    maxLength={2000}
                                    rows={3}
                                />
                            </FormField>

                            <FormField
                                id="product-category"
                                label="Categoría"
                                error={form.errors.category_id}
                            >
                                <Select
                                    value={form.data.category_id}
                                    required
                                    onValueChange={(value) =>
                                        form.setData('category_id', value)
                                    }
                                >
                                    <SelectTrigger
                                        id="product-category"
                                        className="w-full"
                                        aria-invalid={Boolean(
                                            form.errors.category_id,
                                        )}
                                        aria-required="true"
                                        aria-describedby={
                                            form.errors.category_id
                                                ? 'product-category-error'
                                                : undefined
                                        }
                                    >
                                        <SelectValue placeholder="Seleccionar categoría" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectGroup>
                                            {categories.map((category) => (
                                                <SelectItem
                                                    key={category.id}
                                                    value={category.id.toString()}
                                                >
                                                    {category.name}
                                                    {!category.is_active &&
                                                        ' (inactiva)'}
                                                </SelectItem>
                                            ))}
                                        </SelectGroup>
                                    </SelectContent>
                                </Select>
                            </FormField>

                            <FormField
                                id="product-sort-order"
                                label="Orden dentro de la categoría"
                                error={form.errors.sort_order}
                            >
                                <Input
                                    id="product-sort-order"
                                    type="number"
                                    min="0"
                                    step="1"
                                    value={form.data.sort_order}
                                    onChange={(event) =>
                                        form.setData(
                                            'sort_order',
                                            event.target.value,
                                        )
                                    }
                                    aria-invalid={Boolean(
                                        form.errors.sort_order,
                                    )}
                                    aria-describedby={
                                        form.errors.sort_order
                                            ? 'product-sort-order-error'
                                            : undefined
                                    }
                                    required
                                />
                            </FormField>
                        </div>
                    </div>

                    <fieldset className="grid gap-3 rounded-lg border border-border/80 p-4">
                        <legend className="px-1 text-sm font-semibold">
                            Tipo de precio
                        </legend>
                        <Select
                            value={form.data.pricing_family}
                            onValueChange={(value: 'empanada' | 'unit') =>
                                form.setData('pricing_family', value)
                            }
                        >
                            <SelectTrigger id="product-pricing-family">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="empanada">
                                    Empanada: unidad, media docena y docena
                                </SelectItem>
                                <SelectItem value="unit">
                                    Precio por unidad
                                </SelectItem>
                            </SelectContent>
                        </Select>
                    </fieldset>

                    <fieldset className="grid gap-3 rounded-lg border border-border/80 p-4">
                        <legend className="px-1 text-sm font-semibold">
                            {form.data.pricing_family === 'empanada'
                                ? 'Precios cocinados'
                                : 'Precio por unidad'}
                        </legend>
                        <div
                            className={cn(
                                'grid gap-4',
                                form.data.pricing_family === 'empanada' &&
                                    'sm:grid-cols-3',
                            )}
                        >
                            <PriceField
                                id="unit-price"
                                label="Unidad"
                                value={form.data.unit_price}
                                error={form.errors.unit_price}
                                onChange={(value) =>
                                    form.setData('unit_price', value)
                                }
                            />
                            {form.data.pricing_family === 'empanada' && (
                                <>
                                    {!usingSurcharge && (
                                        <PriceField
                                            id="half-dozen-price"
                                            label="Media docena"
                                            value={form.data.half_dozen_price}
                                            error={form.errors.half_dozen_price}
                                            onChange={(value) =>
                                                form.setData(
                                                    'half_dozen_price',
                                                    value,
                                                )
                                            }
                                        />
                                    )}
                                    <div className="sm:col-span-2">
                                        <Button
                                            type="button"
                                            variant="outline"
                                            onClick={() =>
                                                setUsingSurcharge(
                                                    !usingSurcharge,
                                                )
                                            }
                                            className="h-auto min-h-11 w-full px-3 py-2 whitespace-normal text-admin-text-primary"
                                            aria-pressed={usingSurcharge}
                                        >
                                            {usingSurcharge
                                                ? 'Editar precio de media directamente'
                                                : 'Configurar media mediante recargo'}
                                        </Button>
                                        {product?.homogeneous_half_dozen_surcharge && (
                                            <p className="mt-2 text-xs text-admin-text-secondary">
                                                Recargo guardado: $
                                                {
                                                    product.homogeneous_half_dozen_surcharge
                                                }
                                                . No se modifica
                                                automáticamente.
                                            </p>
                                        )}
                                        <FieldError
                                            id="homogeneous-surcharge-error"
                                            message={
                                                form.errors
                                                    .homogeneous_half_dozen_surcharge
                                            }
                                        />
                                    </div>
                                    {usingSurcharge && (
                                        <HomogeneousSurchargePreview
                                            error={
                                                form.errors
                                                    .homogeneous_half_dozen_surcharge ??
                                                form.errors.half_dozen_price
                                            }
                                            dozen={form.data.dozen_price}
                                            value={
                                                form.data
                                                    .homogeneous_half_dozen_surcharge
                                            }
                                            onChange={(value) =>
                                                form.setData(
                                                    'homogeneous_half_dozen_surcharge',
                                                    value,
                                                )
                                            }
                                        />
                                    )}
                                    <PriceField
                                        id="dozen-price"
                                        label="Docena"
                                        value={form.data.dozen_price}
                                        error={form.errors.dozen_price}
                                        onChange={(value) =>
                                            form.setData('dozen_price', value)
                                        }
                                    />
                                </>
                            )}
                        </div>
                    </fieldset>

                    <fieldset
                        className="grid gap-3 rounded-lg border border-border/80 p-4"
                        aria-describedby={
                            tagError ? 'product-tags-error' : undefined
                        }
                    >
                        <legend className="px-1 text-sm font-semibold">
                            Etiquetas
                        </legend>
                        <div className="flex flex-wrap gap-4">
                            {tags.map((tag) => (
                                <label
                                    key={tag.id}
                                    className="flex cursor-pointer items-center gap-2 text-sm"
                                >
                                    <Checkbox
                                        checked={form.data.tags.includes(
                                            tag.id,
                                        )}
                                        onCheckedChange={(checked) =>
                                            toggleTag(tag.id, checked === true)
                                        }
                                        aria-invalid={Boolean(tagError)}
                                        aria-describedby={
                                            tagError
                                                ? 'product-tags-error'
                                                : undefined
                                        }
                                    />
                                    {tag.name}
                                </label>
                            ))}
                        </div>
                        <FieldError
                            id="product-tags-error"
                            message={tagError}
                        />
                    </fieldset>

                    <label className="flex cursor-pointer items-start gap-3 rounded-lg border border-border/80 p-4">
                        <Checkbox
                            checked={form.data.is_available}
                            onCheckedChange={(checked) =>
                                form.setData('is_available', checked === true)
                            }
                            aria-invalid={Boolean(form.errors.is_available)}
                            aria-describedby={
                                form.errors.is_available
                                    ? 'product-availability-error'
                                    : undefined
                            }
                            aria-required="true"
                        />
                        <span>
                            <span className="block text-sm font-semibold">
                                Disponible para la venta
                            </span>
                            <span className="mt-1 block text-xs text-muted-foreground">
                                Los productos disponibles aparecen en el menú
                                público y se pueden vender desde el POS.
                            </span>
                            <FieldError
                                id="product-availability-error"
                                message={form.errors.is_available}
                            />
                        </span>
                    </label>

                    {product && confirmingDelete && (
                        <div
                            ref={deleteConfirmationRef}
                            role="alertdialog"
                            aria-live="assertive"
                            aria-labelledby="delete-product-title"
                            aria-describedby="delete-product-description"
                            tabIndex={-1}
                            className="flex flex-col gap-3 rounded-lg border border-destructive/40 bg-destructive/10 p-4 outline-none focus-visible:ring-2 focus-visible:ring-ring"
                        >
                            <p
                                id="delete-product-title"
                                className="text-sm font-semibold"
                            >
                                ¿Quitar {product.name} del uso habitual?
                            </p>
                            <p
                                id="delete-product-description"
                                className="text-xs text-muted-foreground"
                            >
                                Esta eliminación es reversible. Los pedidos
                                históricos conservan la información del
                                producto.
                            </p>
                            <div className="flex flex-wrap gap-2">
                                <Button
                                    type="button"
                                    variant="destructive"
                                    onClick={deleteProduct}
                                >
                                    Confirmar eliminación
                                </Button>
                                <Button
                                    type="button"
                                    variant="ghost"
                                    onClick={cancelDelete}
                                >
                                    Conservar producto
                                </Button>
                            </div>
                        </div>
                    )}
                </form>

                <DialogFooter className="relative z-10 shrink-0 items-center bg-admin-surface pt-4 sm:justify-between">
                    <div>
                        {product && !confirmingDelete && (
                            <Button
                                ref={removeButtonRef}
                                type="button"
                                variant="ghost"
                                className="text-destructive hover:text-destructive"
                                onClick={requestDelete}
                            >
                                <Trash2 data-icon="inline-start" />
                                Quitar producto
                            </Button>
                        )}
                    </div>
                    <div className="flex flex-col-reverse gap-2 sm:flex-row">
                        <Button
                            type="button"
                            variant="outline"
                            onClick={closeDialog}
                        >
                            Cancelar
                        </Button>
                        <Button
                            type="submit"
                            form="product-form"
                            disabled={form.processing}
                        >
                            {form.processing && (
                                <LoaderCircle
                                    data-icon="inline-start"
                                    className="animate-spin"
                                />
                            )}
                            {product ? 'Guardar cambios' : 'Crear producto'}
                        </Button>
                    </div>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}

function FormField({
    id,
    label,
    error,
    className,
    children,
}: {
    id: string;
    label: string;
    error?: string;
    className?: string;
    children: React.ReactNode;
}) {
    return (
        <div className={cn('flex flex-col gap-2', className)}>
            <Label htmlFor={id}>{label}</Label>
            {children}
            <FieldError id={`${id}-error`} message={error} />
        </div>
    );
}

function PriceField({
    id,
    label,
    value,
    error,
    onChange,
}: {
    id: string;
    label: string;
    value: string;
    error?: string;
    onChange: (value: string) => void;
}) {
    return (
        <FormField id={id} label={label} error={error}>
            <div className="relative">
                <span className="pointer-events-none absolute top-1/2 left-3 -translate-y-1/2 text-sm text-muted-foreground">
                    $
                </span>
                <Input
                    id={id}
                    type="number"
                    min="0"
                    step="1"
                    inputMode="numeric"
                    value={value}
                    onChange={(event) => onChange(event.target.value)}
                    aria-invalid={Boolean(error)}
                    aria-describedby={error ? `${id}-error` : undefined}
                    required
                    className="pl-7"
                />
            </div>
        </FormField>
    );
}

function FieldError({ id, message }: { id: string; message?: string }) {
    if (!message) {
        return null;
    }

    return (
        <p id={id} role="alert" className="text-xs text-destructive">
            {message}
        </p>
    );
}
