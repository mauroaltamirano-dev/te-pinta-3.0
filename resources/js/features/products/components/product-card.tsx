import { Link } from '@inertiajs/react';
import { ImageIcon, Megaphone, Pencil } from 'lucide-react';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { formatMoney } from '@/lib/format';
import type { Product } from '../types';

interface ProductCardProps {
    product: Product;
    /** "Empanadas › Clásicas": where the product lives in the catalog. */
    location: string;
    onEdit: (product: Product) => void;
    onFeature: (product: Product) => void;
}

/** One catalog row: dense enough to scan a whole category without scrolling. */
export function ProductCard({
    product,
    location,
    onEdit,
    onFeature,
}: ProductCardProps) {
    const isVegetarian = product.tags.some(
        (tag) => tag.slug === 'vegetariana' || tag.slug === 'vegetarian',
    );
    const isEmpanada = product.pricing_family === 'empanada';

    return (
        <Card className="grid grid-cols-[auto_minmax(0,1fr)] items-center gap-3 gap-x-4 rounded-[0.5rem] border-admin-border bg-admin-surface px-4 py-3 text-admin-text-primary shadow-none md:grid-cols-[auto_minmax(0,1fr)_minmax(11rem,auto)_auto]">
            <Avatar className="size-14 rounded-lg bg-muted">
                {product.image_url && (
                    <AvatarImage
                        src={product.image_url}
                        alt={product.name}
                        className="object-cover"
                    />
                )}
                <AvatarFallback className="rounded-lg">
                    <ImageIcon
                        aria-hidden="true"
                        className="size-6 text-muted-foreground/55"
                    />
                    <span className="sr-only">Producto sin imagen</span>
                </AvatarFallback>
            </Avatar>

            <div className="min-w-0">
                <h3 className="truncate text-sm leading-snug font-semibold">
                    {product.name}
                </h3>
                <p className="mt-0.5 truncate text-xs text-admin-text-secondary">
                    {location}
                </p>
                {(isVegetarian || !product.is_available) && (
                    <div className="mt-1.5 flex flex-wrap gap-1.5">
                        {isVegetarian && (
                            <Badge variant="secondary">Vegetariano</Badge>
                        )}
                        {!product.is_available && (
                            <Badge variant="destructive">No disponible</Badge>
                        )}
                    </div>
                )}
            </div>

            {/* The dozen is the only empanada price an operator enters; unit and half dozen derive from it. */}
            <dl className="col-span-2 grid grid-cols-[1fr_auto] gap-x-3 gap-y-0.5 text-xs md:col-span-1">
                <dt className="text-admin-text-secondary">
                    {isEmpanada ? 'Docena cocinada' : 'Precio por unidad'}
                </dt>
                <dd className="text-right text-sm font-semibold tabular-nums">
                    {formatMoney(
                        isEmpanada ? product.dozen_price : product.unit_price,
                    )}
                </dd>
                {isEmpanada && (
                    <>
                        <dt className="text-admin-text-secondary">
                            Unidad · media docena
                        </dt>
                        <dd className="text-right text-admin-text-secondary tabular-nums">
                            {formatMoney(product.unit_price)} ·{' '}
                            {formatMoney(product.half_dozen_price)}
                        </dd>
                    </>
                )}
            </dl>

            <div className="col-span-2 flex flex-wrap items-center gap-x-3 gap-y-1 md:col-span-1 md:justify-end">
                <Button
                    type="button"
                    variant="outline"
                    size="sm"
                    onClick={() => onEdit(product)}
                >
                    <Pencil data-icon="inline-start" />
                    Editar producto
                </Button>
                <Button
                    type="button"
                    variant="ghost"
                    size="sm"
                    className="text-admin-accent-text"
                    onClick={() => onFeature(product)}
                >
                    <Megaphone className="size-4" aria-hidden="true" />
                    Destacar
                </Button>
                <Link
                    href={`/admin/inventory/product-consumption/${product.id}`}
                    className="text-xs text-admin-text-secondary underline underline-offset-2 hover:text-admin-text-primary"
                >
                    Configurar consumo físico
                </Link>
            </div>
        </Card>
    );
}
