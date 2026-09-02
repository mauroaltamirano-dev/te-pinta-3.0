import { Head, Link } from '@inertiajs/react';
import { Minus, Plus, ShoppingBag } from 'lucide-react';
import { useEffect, useMemo, useState } from 'react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { useCart } from '@/features/orders/cart-context';
import type { PublicProduct } from '@/features/orders/types';

const money = new Intl.NumberFormat('es-AR', {
    style: 'currency',
    currency: 'ARS',
    maximumFractionDigits: 0,
});
export default function Menu() {
    const [products, setProducts] = useState<PublicProduct[]>([]);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState('');
    const [category, setCategory] = useState<string>('all');
    const { items, addProduct, setProductQuantity, count } = useCart();
    useEffect(() => {
        fetch('/menu/catalog', { headers: { Accept: 'application/json' } })
            .then(async (response) => {
                if (!response.ok) {
                    throw new Error();
                }

                return response.json() as Promise<{ data: PublicProduct[] }>;
            })
            .then((payload) => setProducts(payload.data))
            .catch(() =>
                setError('No se pudo cargar el menú. Intentá nuevamente.'),
            )
            .finally(() => setLoading(false));
    }, []);
    const categories = useMemo(
        () =>
            Array.from(
                new Map(
                    products.map((product) => [
                        product.category.slug,
                        product.category,
                    ]),
                ).values(),
            ),
        [products],
    );
    const visible =
        category === 'all'
            ? products
            : products.filter((product) => product.category.slug === category);

    return (
        <>
            <Head title="Menú" />
            <section className="mx-auto grid max-w-6xl gap-6 px-4 py-8 sm:px-6 sm:py-12">
                <header className="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                    <div>
                        <p className="text-xs font-semibold tracking-[0.18em] text-primary uppercase">
                            Nuestro menú
                        </p>
                        <h1 className="mt-1 font-display text-3xl font-bold">
                            Menú
                        </h1>
                        <p className="mt-2 text-sm text-muted-foreground">
                            Agregá la cantidad que quieras. Te Pinta calcula la
                            combinación y el precio final.
                        </p>
                    </div>
                    <Button asChild>
                        <Link href="/cart">
                            <ShoppingBag /> Ver carrito{' '}
                            {count > 0 && `(${count})`}
                        </Link>
                    </Button>
                </header>
                <div
                    role="group"
                    aria-label="Categorías del menú"
                    className="flex gap-2 overflow-x-auto"
                >
                    <Button
                        variant={category === 'all' ? 'default' : 'outline'}
                        size="sm"
                        onClick={() => setCategory('all')}
                    >
                        Todas
                    </Button>
                    {categories.map((item) => (
                        <Button
                            key={item.slug}
                            variant={
                                category === item.slug ? 'default' : 'outline'
                            }
                            size="sm"
                            onClick={() => setCategory(item.slug)}
                        >
                            {item.name}
                        </Button>
                    ))}
                </div>
                {loading ? (
                    <p
                        role="status"
                        aria-busy="true"
                        className="py-16 text-center text-muted-foreground"
                    >
                        Cargando menú…
                    </p>
                ) : error ? (
                    <p
                        role="alert"
                        className="py-16 text-center text-destructive"
                    >
                        {error}
                    </p>
                ) : (
                    <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                        {visible.map((product) => {
                            const quantity =
                                items.find(
                                    (line) =>
                                        line.product_slug === product.slug,
                                )?.quantity ?? 0;

                            return (
                                <Card
                                    key={product.slug}
                                    className="overflow-hidden"
                                >
                                    <CardHeader>
                                        <div className="flex items-start justify-between gap-3">
                                            <CardTitle>
                                                {product.name}
                                            </CardTitle>
                                            {quantity > 0 && (
                                                <Badge>
                                                    {quantity} en el carrito
                                                </Badge>
                                            )}
                                        </div>
                                        <p className="text-sm text-muted-foreground">
                                            {product.description}
                                        </p>
                                    </CardHeader>
                                    <CardContent className="grid gap-4">
                                        <p className="text-xs font-medium text-muted-foreground">
                                            Precios cocinados
                                        </p>
                                        <div className="grid grid-cols-3 gap-2 text-xs">
                                            <span>
                                                Unidad
                                                <br />
                                                <strong>
                                                    {money.format(
                                                        product.prices.unit,
                                                    )}
                                                </strong>
                                            </span>
                                            <span>
                                                Media docena
                                                <br />
                                                <strong>
                                                    {money.format(
                                                        product.prices
                                                            .half_dozen,
                                                    )}
                                                </strong>
                                            </span>
                                            <span>
                                                Docena
                                                <br />
                                                <strong>
                                                    {money.format(
                                                        product.prices.dozen,
                                                    )}
                                                </strong>
                                            </span>
                                        </div>
                                        <div className="flex flex-wrap gap-2">
                                            <Button
                                                size="sm"
                                                aria-label={`Agregar una unidad de ${product.name}`}
                                                onClick={() =>
                                                    addProduct(product.slug, 1)
                                                }
                                            >
                                                <Plus /> Unidad
                                            </Button>
                                            <Button
                                                size="sm"
                                                variant="outline"
                                                onClick={() =>
                                                    addProduct(product.slug, 6)
                                                }
                                            >
                                                + Media docena
                                            </Button>
                                            <Button
                                                size="sm"
                                                variant="outline"
                                                onClick={() =>
                                                    addProduct(product.slug, 12)
                                                }
                                            >
                                                + Docena
                                            </Button>
                                            {quantity > 0 && (
                                                <Button
                                                    size="icon"
                                                    variant="ghost"
                                                    aria-label={`Quitar una unidad de ${product.name}`}
                                                    onClick={() =>
                                                        setProductQuantity(
                                                            product.slug,
                                                            quantity - 1,
                                                        )
                                                    }
                                                >
                                                    <Minus />
                                                </Button>
                                            )}
                                        </div>
                                    </CardContent>
                                </Card>
                            );
                        })}
                    </div>
                )}
            </section>
        </>
    );
}
