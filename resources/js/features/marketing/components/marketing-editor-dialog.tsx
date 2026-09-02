import { useForm } from '@inertiajs/react';
import { LoaderCircle } from 'lucide-react';
import { useMemo } from 'react';
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
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import type {
    AdminFeaturedProduct,
    FeaturedProductFormDefaults,
    FeaturedProductPublicView,
    SelectableFeaturedProduct,
} from '../types';
import { FeaturedProductCard } from './featured-product-card';

interface FeaturedProductFormData {
    product_id: string;
    headline: string;
    short_copy: string;
    starts_at: string;
    ends_at: string;
    is_enabled: boolean;
}

export function MarketingEditorDialog({
    open,
    featured,
    products,
    defaults,
    timezone,
    onOpenChange,
}: {
    open: boolean;
    featured: AdminFeaturedProduct | null;
    products: SelectableFeaturedProduct[];
    defaults: FeaturedProductFormDefaults;
    timezone: string;
    onOpenChange: (open: boolean) => void;
}) {
    const productOptions = useMemo(() => {
        if (
            !featured ||
            products.some((product) => product.id === featured.product.id)
        ) {
            return products;
        }

        return [
            ...products,
            {
                id: featured.product.id,
                product: featured.public_view.product,
            },
        ];
    }, [featured, products]);
    const form = useForm<FeaturedProductFormData>({
        product_id: featured?.product.id.toString() ?? '',
        headline: featured?.headline ?? '',
        short_copy: featured?.short_copy ?? '',
        starts_at: featured?.starts_at_local ?? defaults.starts_at,
        ends_at: featured?.ends_at_local ?? defaults.ends_at ?? '',
        is_enabled: featured?.is_enabled ?? defaults.is_enabled,
    });
    const selectedProduct = productOptions.find(
        (product) => product.id.toString() === form.data.product_id,
    );
    const preview: FeaturedProductPublicView | null = selectedProduct
        ? {
              headline: form.data.headline.trim() || null,
              short_copy: form.data.short_copy.trim() || null,
              product: selectedProduct.product,
          }
        : null;
    const submit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();

        const options = {
            preserveScroll: true,
            onSuccess: () => onOpenChange(false),
        };

        if (featured) {
            form.patch(
                `/admin/marketing/featured-products/${featured.id}`,
                options,
            );

            return;
        }

        form.post('/admin/marketing/featured-products', options);
    };

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="admin-shell dark max-h-[calc(100vh-2rem)] overflow-y-auto border-admin-border bg-admin-surface sm:max-w-5xl">
                <DialogHeader>
                    <DialogTitle>
                        {featured
                            ? 'Editar recomendación'
                            : 'Nueva recomendación'}
                    </DialogTitle>
                    <DialogDescription>
                        Programá un producto destacado sin modificar su precio
                        ni disponibilidad.
                    </DialogDescription>
                </DialogHeader>

                <form onSubmit={submit} className="grid gap-6">
                    <div className="grid min-w-0 gap-6 lg:grid-cols-[minmax(0,0.9fr)_minmax(24rem,1.1fr)]">
                        <div className="grid content-start gap-4">
                            <Field
                                label="Producto"
                                htmlFor="featured-product"
                                error={form.errors.product_id}
                            >
                                <Select
                                    value={form.data.product_id}
                                    onValueChange={(value) =>
                                        form.setData('product_id', value)
                                    }
                                >
                                    <SelectTrigger
                                        id="featured-product"
                                        className="w-full"
                                        aria-invalid={Boolean(
                                            form.errors.product_id,
                                        )}
                                        aria-describedby={
                                            form.errors.product_id
                                                ? 'featured-product-error'
                                                : undefined
                                        }
                                    >
                                        <SelectValue placeholder="Seleccionar producto" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {productOptions.map((product) => (
                                            <SelectItem
                                                key={product.id}
                                                value={product.id.toString()}
                                            >
                                                {product.product.name}
                                                {featured?.product.id ===
                                                    product.id &&
                                                    featured.visibility_reason &&
                                                    ' · no disponible'}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </Field>

                            <Field
                                label="Título destacado"
                                htmlFor="featured-headline"
                                error={form.errors.headline}
                                help="Opcional · máximo 80 caracteres."
                            >
                                <Input
                                    id="featured-headline"
                                    value={form.data.headline}
                                    maxLength={80}
                                    onChange={(event) =>
                                        form.setData(
                                            'headline',
                                            event.target.value,
                                        )
                                    }
                                    placeholder="Nuestra recomendación de hoy"
                                    aria-invalid={Boolean(form.errors.headline)}
                                    aria-describedby={
                                        form.errors.headline
                                            ? 'featured-headline-error'
                                            : 'featured-headline-help'
                                    }
                                />
                            </Field>

                            <Field
                                label="Texto corto"
                                htmlFor="featured-copy"
                                error={form.errors.short_copy}
                                help="Opcional · máximo 240 caracteres."
                            >
                                <Textarea
                                    id="featured-copy"
                                    value={form.data.short_copy}
                                    maxLength={240}
                                    rows={4}
                                    onChange={(event) =>
                                        form.setData(
                                            'short_copy',
                                            event.target.value,
                                        )
                                    }
                                    placeholder="Contá por qué vale la pena probarla."
                                    aria-invalid={Boolean(
                                        form.errors.short_copy,
                                    )}
                                    aria-describedby={
                                        form.errors.short_copy
                                            ? 'featured-copy-error'
                                            : 'featured-copy-help'
                                    }
                                />
                            </Field>

                            <div className="grid gap-4 sm:grid-cols-2">
                                <Field
                                    label="Inicio"
                                    htmlFor="featured-starts-at"
                                    error={form.errors.starts_at}
                                >
                                    <Input
                                        id="featured-starts-at"
                                        type="datetime-local"
                                        required
                                        value={form.data.starts_at}
                                        onChange={(event) =>
                                            form.setData(
                                                'starts_at',
                                                event.target.value,
                                            )
                                        }
                                        className="[color-scheme:dark]"
                                        aria-invalid={Boolean(
                                            form.errors.starts_at,
                                        )}
                                        aria-describedby={
                                            form.errors.starts_at
                                                ? 'featured-starts-at-error'
                                                : undefined
                                        }
                                    />
                                </Field>
                                <Field
                                    label="Fin"
                                    htmlFor="featured-ends-at"
                                    error={form.errors.ends_at}
                                >
                                    <Input
                                        id="featured-ends-at"
                                        type="datetime-local"
                                        min={form.data.starts_at || undefined}
                                        value={form.data.ends_at}
                                        onChange={(event) =>
                                            form.setData(
                                                'ends_at',
                                                event.target.value,
                                            )
                                        }
                                        className="[color-scheme:dark]"
                                        aria-invalid={Boolean(
                                            form.errors.ends_at,
                                        )}
                                        aria-describedby={
                                            form.errors.ends_at
                                                ? 'featured-ends-at-error'
                                                : undefined
                                        }
                                    />
                                </Field>
                            </div>
                            <p className="-mt-2 text-xs text-admin-text-tertiary">
                                Horario del negocio: {timezone}. Si no indicás
                                un fin, la recomendación continúa
                                indefinidamente.
                            </p>

                            <label className="flex min-h-16 cursor-pointer items-center justify-between gap-4 rounded-xl border border-admin-border bg-admin-surface-raised px-4">
                                <span>
                                    <span className="block text-sm font-semibold text-white">
                                        Recomendación habilitada
                                    </span>
                                    <span className="mt-0.5 block text-xs text-admin-text-tertiary">
                                        Las fechas determinan cuándo se muestra.
                                    </span>
                                </span>
                                <Checkbox
                                    checked={form.data.is_enabled}
                                    onCheckedChange={(checked) =>
                                        form.setData(
                                            'is_enabled',
                                            checked === true,
                                        )
                                    }
                                    aria-label="Habilitar recomendación"
                                />
                            </label>
                        </div>

                        <section
                            data-testid="marketing-editor-preview"
                            className="min-w-0 rounded-2xl border border-admin-border bg-admin-canvas p-3 sm:p-4"
                        >
                            <p className="mb-3 text-xs font-semibold tracking-[0.16em] text-admin-accent uppercase">
                                Vista previa pública
                            </p>
                            {preview ? (
                                <FeaturedProductCard featured={preview} />
                            ) : (
                                <div className="grid min-h-80 place-items-center rounded-2xl border border-dashed border-admin-border px-6 text-center text-sm text-admin-text-secondary">
                                    Elegí un producto para ver la recomendación.
                                </div>
                            )}
                        </section>
                    </div>

                    <DialogFooter>
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => onOpenChange(false)}
                        >
                            Cancelar
                        </Button>
                        <Button type="submit" disabled={form.processing}>
                            {form.processing && (
                                <LoaderCircle
                                    className="animate-spin"
                                    aria-hidden="true"
                                />
                            )}
                            Guardar recomendación
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

function Field({
    label,
    htmlFor,
    error,
    help,
    children,
}: {
    label: string;
    htmlFor?: string;
    error?: string;
    help?: string;
    children: React.ReactNode;
}) {
    return (
        <div className="grid gap-2">
            <Label htmlFor={htmlFor}>{label}</Label>
            {children}
            {error ? (
                <p
                    id={htmlFor ? `${htmlFor}-error` : undefined}
                    role="alert"
                    className="text-sm text-destructive"
                >
                    {error}
                </p>
            ) : help ? (
                <p
                    id={htmlFor ? `${htmlFor}-help` : undefined}
                    className="text-xs text-admin-text-tertiary"
                >
                    {help}
                </p>
            ) : null}
        </div>
    );
}
