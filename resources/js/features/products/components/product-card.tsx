import { ImageIcon, Megaphone, Pencil } from 'lucide-react';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardFooter,
    CardHeader,
} from '@/components/ui/card';
import type { Product } from '../types';
import { formatPesos } from '../utils';

interface ProductCardProps {
    product: Product;
    onEdit: (product: Product) => void;
    onFeature: (product: Product) => void;
}

export function ProductCard({ product, onEdit, onFeature }: ProductCardProps) {
    const isVegetarian = product.tags.some(
        (tag) => tag.slug === 'vegetariana' || tag.slug === 'vegetarian',
    );

    return (
        <Card className="gap-0 overflow-hidden rounded-[0.5rem] border-admin-border bg-admin-surface py-0 text-admin-text-primary shadow-none">
            <CardHeader className="items-center gap-3 px-5 pt-5 pb-4 text-center">
                <Avatar className="size-24 border-4 border-muted bg-muted shadow-lg shadow-black/20">
                    {product.image_url && (
                        <AvatarImage
                            src={product.image_url}
                            alt={product.name}
                            className="object-cover"
                        />
                    )}
                    <AvatarFallback>
                        <ImageIcon
                            aria-hidden="true"
                            className="size-8 text-muted-foreground/55"
                        />
                        <span className="sr-only">Producto sin imagen</span>
                    </AvatarFallback>
                </Avatar>
                <div className="flex min-h-14 flex-col items-center gap-2">
                    <h3 className="line-clamp-2 text-sm leading-snug font-semibold">
                        {product.name}
                    </h3>
                    <div className="flex flex-wrap justify-center gap-1.5">
                        {isVegetarian && (
                            <Badge variant="secondary">Vegetariano</Badge>
                        )}
                        {!product.is_available && (
                            <Badge variant="destructive">No disponible</Badge>
                        )}
                    </div>
                </div>
            </CardHeader>
            <CardContent className="grid gap-2 bg-transparent px-5 pb-5 text-xs">
                <PriceRow
                    label={
                        product.pricing_family === 'empanada'
                            ? 'Unidad cocinada'
                            : 'Precio por unidad'
                    }
                    value={product.unit_price}
                />
                {product.pricing_family === 'empanada' && (
                    <>
                        <PriceRow
                            label="Media docena cocinada"
                            value={product.half_dozen_price}
                        />
                        <PriceRow
                            label="Docena cocinada"
                            value={product.dozen_price}
                        />
                    </>
                )}
            </CardContent>
            <CardFooter className="mt-auto grid h-auto auto-cols-fr grid-flow-col bg-admin-action p-0">
                <Button
                    type="button"
                    variant="ghost"
                    className="h-[3.25rem] w-full rounded-none bg-transparent text-admin-accent hover:bg-transparent hover:text-admin-accent"
                    onClick={() => onEdit(product)}
                >
                    <Pencil data-icon="inline-start" />
                    Editar producto
                </Button>
                <Button
                    type="button"
                    variant="ghost"
                    className="h-[3.25rem] rounded-none text-admin-accent"
                    onClick={() => onFeature(product)}
                >
                    <Megaphone className="size-4" aria-hidden="true" />
                    Destacar
                </Button>
            </CardFooter>
        </Card>
    );
}

function PriceRow({ label, value }: { label: string; value: number }) {
    return (
        <div className="flex items-center justify-between gap-3">
            <span className="text-admin-text-secondary">{label}</span>
            <span className="font-medium tabular-nums">
                {formatPesos(value)}
            </span>
        </div>
    );
}
