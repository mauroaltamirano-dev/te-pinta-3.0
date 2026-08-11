import { ImageIcon, Pencil } from 'lucide-react';
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
}

export function ProductCard({ product, onEdit }: ProductCardProps) {
    const isVegetarian = product.tags.some(
        (tag) => tag.slug === 'vegetariana' || tag.slug === 'vegetarian',
    );

    return (
        <Card className="group gap-4 overflow-hidden border-border/80 bg-card/70 py-5 shadow-none transition-[border-color,transform] hover:-translate-y-0.5 hover:border-primary/45">
            <CardHeader className="items-center gap-3 px-5 text-center">
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
                        <span className="sr-only">No product image</span>
                    </AvatarFallback>
                </Avatar>
                <div className="flex min-h-14 flex-col items-center gap-2">
                    <h3 className="line-clamp-2 text-sm leading-snug font-semibold">
                        {product.name}
                    </h3>
                    <div className="flex flex-wrap justify-center gap-1.5">
                        {isVegetarian && (
                            <Badge variant="secondary">Vegetarian</Badge>
                        )}
                        {!product.is_available && (
                            <Badge variant="destructive">Unavailable</Badge>
                        )}
                    </div>
                </div>
            </CardHeader>
            <CardContent className="grid gap-2 px-5 text-xs">
                <PriceRow label="Unit" value={product.unit_price} />
                <PriceRow label="Half dozen" value={product.half_dozen_price} />
                <PriceRow label="Dozen" value={product.dozen_price} />
            </CardContent>
            <CardFooter className="mt-auto px-4 pb-0">
                <Button
                    type="button"
                    variant="secondary"
                    className="w-full border border-primary/20 bg-primary/12 text-primary hover:bg-primary/20"
                    onClick={() => onEdit(product)}
                >
                    <Pencil data-icon="inline-start" />
                    Edit product
                </Button>
            </CardFooter>
        </Card>
    );
}

function PriceRow({ label, value }: { label: string; value: number }) {
    return (
        <div className="flex items-center justify-between gap-3">
            <span className="text-muted-foreground">{label}</span>
            <span className="font-medium tabular-nums">
                {formatPesos(value)}
            </span>
        </div>
    );
}
