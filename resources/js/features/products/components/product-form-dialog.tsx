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

interface ProductFormData {
    name: string;
    description: string;
    category_id: string;
    tags: number[];
    unit_price: string;
    half_dozen_price: string;
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
        description: product?.description ?? '',
        category_id:
            product?.category_id.toString() ??
            defaultCategoryId?.toString() ??
            '',
        tags: product?.tags.map((tag) => tag.id) ?? [],
        unit_price: product?.unit_price.toString() ?? '',
        half_dozen_price: product?.half_dozen_price.toString() ?? '',
        dozen_price: product?.dozen_price.toString() ?? '',
        is_available: product?.is_available ?? true,
        sort_order: product?.sort_order.toString() ?? '0',
        image: null,
        remove_image: false,
    });
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

        const options = {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: closeDialog,
        };

        if (product) {
            form.transform((data) => ({ ...data, _method: 'put' }));
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
            <DialogContent className="dark admin-shell products-workspace max-h-[92vh] overflow-y-auto sm:max-w-3xl">
                <DialogHeader>
                    <DialogTitle>
                        {product ? 'Edit product' : 'Add product'}
                    </DialogTitle>
                    <DialogDescription>
                        Base prices are stored in pesos. Cooking surcharges are
                        applied separately by the backend.
                    </DialogDescription>
                </DialogHeader>

                <form onSubmit={submit} className="flex flex-col gap-6">
                    <div className="grid gap-6 md:grid-cols-[9rem_1fr]">
                        <div className="flex flex-col items-center gap-3">
                            <Avatar className="size-28 border-4 border-muted bg-muted">
                                {previewUrl && (
                                    <AvatarImage
                                        src={previewUrl}
                                        alt="Product image preview"
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
                                Choose image
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
                                        ? 'Restore current image'
                                        : 'Clear image'}
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
                                label="Name"
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
                                id="product-description"
                                label="Short description"
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
                                id="product-category"
                                label="Category"
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
                                        <SelectValue placeholder="Select category" />
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
                                                        ' (inactive)'}
                                                </SelectItem>
                                            ))}
                                        </SelectGroup>
                                    </SelectContent>
                                </Select>
                            </FormField>

                            <FormField
                                id="product-sort-order"
                                label="Order within category"
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
                            Base prices
                        </legend>
                        <div className="grid gap-4 sm:grid-cols-3">
                            <PriceField
                                id="unit-price"
                                label="Unit"
                                value={form.data.unit_price}
                                error={form.errors.unit_price}
                                onChange={(value) =>
                                    form.setData('unit_price', value)
                                }
                            />
                            <PriceField
                                id="half-dozen-price"
                                label="Half dozen"
                                value={form.data.half_dozen_price}
                                error={form.errors.half_dozen_price}
                                onChange={(value) =>
                                    form.setData('half_dozen_price', value)
                                }
                            />
                            <PriceField
                                id="dozen-price"
                                label="Dozen"
                                value={form.data.dozen_price}
                                error={form.errors.dozen_price}
                                onChange={(value) =>
                                    form.setData('dozen_price', value)
                                }
                            />
                        </div>
                    </fieldset>

                    <fieldset
                        className="grid gap-3 rounded-lg border border-border/80 p-4"
                        aria-describedby={
                            tagError ? 'product-tags-error' : undefined
                        }
                    >
                        <legend className="px-1 text-sm font-semibold">
                            Tags
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
                                Available for sale
                            </span>
                            <span className="mt-1 block text-xs text-muted-foreground">
                                Available products appear on the public menu and
                                can be sold in the POS.
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
                                Remove {product.name} from normal use?
                            </p>
                            <p
                                id="delete-product-description"
                                className="text-xs text-muted-foreground"
                            >
                                This is a soft delete. Historical orders keep
                                their product information.
                            </p>
                            <div className="flex flex-wrap gap-2">
                                <Button
                                    type="button"
                                    variant="destructive"
                                    onClick={deleteProduct}
                                >
                                    Confirm removal
                                </Button>
                                <Button
                                    type="button"
                                    variant="ghost"
                                    onClick={cancelDelete}
                                >
                                    Keep product
                                </Button>
                            </div>
                        </div>
                    )}

                    <DialogFooter className="items-center sm:justify-between">
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
                                    Remove product
                                </Button>
                            )}
                        </div>
                        <div className="flex flex-col-reverse gap-2 sm:flex-row">
                            <Button
                                type="button"
                                variant="outline"
                                onClick={closeDialog}
                            >
                                Cancel
                            </Button>
                            <Button type="submit" disabled={form.processing}>
                                {form.processing && (
                                    <LoaderCircle
                                        data-icon="inline-start"
                                        className="animate-spin"
                                    />
                                )}
                                {product ? 'Save changes' : 'Create product'}
                            </Button>
                        </div>
                    </DialogFooter>
                </form>
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
