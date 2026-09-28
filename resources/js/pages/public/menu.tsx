import { Head, Link } from '@inertiajs/react';
import { ArrowRight, Minus, Plus, ShoppingBag } from 'lucide-react';
import { useEffect, useMemo, useState } from 'react';
import { Button } from '@/components/ui/button';
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
    const [category, setCategory] = useState('all');
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
            <section
                className={`mx-auto max-w-7xl px-4 pt-10 sm:px-8 sm:pt-14 lg:px-12 ${count > 0 ? 'pb-32 sm:pb-18' : 'pb-14 sm:pb-18'}`}
            >
                <header className="flex flex-col gap-6 border-b border-border pb-8 sm:flex-row sm:items-end sm:justify-between sm:pb-10">
                    <div>
                        <p className="text-xs font-bold tracking-[0.2em] text-white uppercase">
                            Te Pinta · Para pedir
                        </p>
                        <h1 className="mt-4 font-display text-5xl leading-none font-black tracking-tight sm:text-6xl lg:text-7xl">
                            Nuestro menú
                            <span className="text-public-earth">.</span>
                        </h1>
                        <p className="mt-4 max-w-xl text-sm leading-6 text-white sm:text-base">
                            Elegí tus empanadas y sumá la cantidad que quieras.
                            El precio final se confirma al revisar el carrito.
                        </p>
                    </div>
                    <Button
                        asChild
                        size="lg"
                        className="hidden min-h-11 rounded-full bg-public-ink px-6 text-white hover:bg-public-ink/90 sm:inline-flex"
                    >
                        <Link href="/cart">
                            <ShoppingBag aria-hidden="true" /> Ver carrito
                            {count > 0 && ` (${count})`}
                        </Link>
                    </Button>
                </header>

                {!loading && !error && products.length > 0 && (
                    <div className="sticky top-0 z-10 -mx-4 border-b border-border bg-background/95 px-4 py-4 backdrop-blur-sm sm:-mx-8 sm:px-8 lg:-mx-12 lg:px-12">
                        <div
                            role="group"
                            aria-label="Categorías del menú"
                            className="flex gap-2 overflow-x-auto pb-1"
                        >
                            <Button
                                type="button"
                                size="sm"
                                variant={
                                    category === 'all' ? 'default' : 'outline'
                                }
                                aria-pressed={category === 'all'}
                                className="min-h-11 shrink-0 rounded-full px-5 shadow-none aria-[pressed=true]:bg-public-ink aria-[pressed=true]:text-white aria-[pressed=true]:hover:bg-public-ink/90"
                                onClick={() => setCategory('all')}
                            >
                                Todas
                            </Button>
                            {categories.map((item) => (
                                <Button
                                    key={item.slug}
                                    type="button"
                                    size="sm"
                                    variant={
                                        category === item.slug
                                            ? 'default'
                                            : 'outline'
                                    }
                                    aria-pressed={category === item.slug}
                                    className="min-h-11 shrink-0 rounded-full px-5 shadow-none aria-[pressed=true]:bg-public-ink aria-[pressed=true]:text-white aria-[pressed=true]:hover:bg-public-ink/90"
                                    onClick={() => setCategory(item.slug)}
                                >
                                    {item.name}
                                </Button>
                            ))}
                        </div>
                    </div>
                )}

                {loading ? (
                    <p
                        role="status"
                        aria-busy="true"
                        className="py-24 text-center text-white"
                    >
                        Cargando menú…
                    </p>
                ) : error ? (
                    <p
                        role="alert"
                        className="rounded-xl bg-public-earth py-24 text-center text-destructive"
                    >
                        {error}
                    </p>
                ) : products.length === 0 ? (
                    <div className="mx-auto max-w-md py-24 text-center">
                        <h2 className="font-display text-3xl font-bold">
                            Por ahora no hay productos disponibles.
                        </h2>
                        <p className="mt-3 text-white">
                            Volvé a revisar el menú más tarde.
                        </p>
                    </div>
                ) : (
                    <div className="pt-7 sm:pt-10">
                        <p
                            className="mb-5 text-sm font-medium text-white"
                            aria-live="polite"
                        >
                            {category === 'all'
                                ? 'Todo el menú'
                                : categories.find(
                                      (item) => item.slug === category,
                                  )?.name}
                            <span className="ml-2 text-white">
                                · {visible.length}{' '}
                                {visible.length === 1 ? 'opción' : 'opciones'}
                            </span>
                        </p>
                        <div className="grid gap-5 sm:grid-cols-2 lg:grid-cols-3 lg:gap-6">
                            {visible.map((product) => {
                                const quantity =
                                    items.find(
                                        (line) =>
                                            line.product_slug === product.slug,
                                    )?.quantity ?? 0;

                                return (
                                    <article
                                        key={product.slug}
                                        data-testid="public-product-card"
                                        className="flex min-w-0 flex-col overflow-hidden rounded-[1.75rem] border border-border bg-card text-card-foreground"
                                    >
                                        <div className="relative aspect-[4/3] overflow-hidden bg-secondary">
                                            {product.image_url ? (
                                                <img
                                                    src={product.image_url}
                                                    alt={product.name}
                                                    loading="lazy"
                                                    className="size-full object-cover"
                                                />
                                            ) : (
                                                <div
                                                    className="relative grid size-full place-items-center overflow-hidden text-public-earth-ink"
                                                    aria-hidden="true"
                                                >
                                                    <span className="absolute size-[80%] rounded-full border border-primary/25" />
                                                    <span className="absolute size-[58%] rounded-full border border-primary/35" />
                                                    <span className="relative font-display text-7xl font-black tracking-tight sm:text-8xl">
                                                        {product.name
                                                            .charAt(0)
                                                            .toLocaleUpperCase(
                                                                'es-AR',
                                                            )}
                                                    </span>
                                                </div>
                                            )}
                                            {quantity > 0 && (
                                                <span
                                                    className="absolute top-4 right-4 rounded-full bg-public-ink px-3 py-1.5 text-xs font-semibold text-white shadow-sm"
                                                    aria-live="polite"
                                                >
                                                    {quantity} en el carrito
                                                </span>
                                            )}
                                        </div>
                                        <div className="flex flex-1 flex-col p-5 sm:p-6">
                                            <p className="text-xs font-bold tracking-[0.16em] text-public-earth-ink uppercase">
                                                {product.category.name}
                                            </p>
                                            <h2 className="mt-2 font-display text-3xl leading-none font-bold tracking-tight">
                                                <Link
                                                    href={`/menu/${encodeURIComponent(product.slug)}`}
                                                    className="underline-offset-4 hover:underline focus-visible:underline"
                                                >
                                                    {product.name}
                                                </Link>
                                            </h2>
                                            {product.tags.length > 0 && (
                                                <div className="mt-3 flex flex-wrap gap-1.5">
                                                    {product.tags.map((tag) => (
                                                        <span
                                                            key={tag.slug}
                                                            className="rounded-full border border-border px-2.5 py-1 text-xs font-medium text-muted-foreground"
                                                        >
                                                            {tag.name}
                                                        </span>
                                                    ))}
                                                </div>
                                            )}
                                            {product.description && (
                                                <p className="mt-3 line-clamp-2 text-sm leading-6 text-muted-foreground">
                                                    {product.description}
                                                </p>
                                            )}
                                            <div className="mt-auto pt-6">
                                                <div className="border-t border-border pt-5">
                                                    <p className="text-xs font-semibold tracking-wide text-muted-foreground uppercase">
                                                        Precios cocinados
                                                    </p>
                                                    <dl className="mt-3 grid grid-cols-3 gap-2 text-xs">
                                                        <div>
                                                            <dt className="text-muted-foreground">
                                                                Unidad
                                                            </dt>
                                                            <dd className="mt-1 font-bold tabular-nums">
                                                                {money.format(
                                                                    product
                                                                        .prices
                                                                        .unit,
                                                                )}
                                                            </dd>
                                                        </div>
                                                        <div>
                                                            <dt className="text-muted-foreground">
                                                                Media docena
                                                            </dt>
                                                            <dd className="mt-1 font-bold tabular-nums">
                                                                {money.format(
                                                                    product
                                                                        .prices
                                                                        .half_dozen,
                                                                )}
                                                            </dd>
                                                        </div>
                                                        <div>
                                                            <dt className="text-muted-foreground">
                                                                Docena
                                                            </dt>
                                                            <dd className="mt-1 font-bold tabular-nums">
                                                                {money.format(
                                                                    product
                                                                        .prices
                                                                        .dozen,
                                                                )}
                                                            </dd>
                                                        </div>
                                                    </dl>
                                                </div>
                                                {quantity > 0 && (
                                                    <div className="mt-5 flex items-center justify-between gap-3 rounded-xl bg-secondary px-3 py-2">
                                                        <span
                                                            className="text-sm font-semibold"
                                                            aria-live="polite"
                                                        >
                                                            En tu carrito:{' '}
                                                            {quantity}
                                                        </span>
                                                        <Button
                                                            type="button"
                                                            size="icon"
                                                            variant="outline"
                                                            className="size-11 shrink-0 rounded-full bg-public-earth text-public-ink hover:bg-white"
                                                            aria-label={`Quitar una unidad de ${product.name}`}
                                                            onClick={() =>
                                                                setProductQuantity(
                                                                    product.slug,
                                                                    quantity -
                                                                        1,
                                                                )
                                                            }
                                                        >
                                                            <Minus aria-hidden="true" />
                                                        </Button>
                                                    </div>
                                                )}
                                                <div className="mt-5 grid grid-cols-2 gap-2">
                                                    <Button
                                                        asChild
                                                        variant="link"
                                                        className="col-span-2 min-h-11 justify-start px-0 text-public-earth-ink"
                                                    >
                                                        <Link
                                                            href={`/menu/${encodeURIComponent(product.slug)}`}
                                                        >
                                                            Ver detalles
                                                        </Link>
                                                    </Button>
                                                    <Button
                                                        type="button"
                                                        size="lg"
                                                        className="col-span-2 min-h-11 rounded-full"
                                                        aria-label={`Agregar una unidad de ${product.name}`}
                                                        onClick={() =>
                                                            addProduct(
                                                                product.slug,
                                                                1,
                                                            )
                                                        }
                                                    >
                                                        <Plus aria-hidden="true" />{' '}
                                                        Unidad
                                                    </Button>
                                                    <Button
                                                        type="button"
                                                        size="lg"
                                                        variant="outline"
                                                        className="min-h-11 rounded-full bg-public-earth px-2 text-public-ink hover:bg-white"
                                                        onClick={() =>
                                                            addProduct(
                                                                product.slug,
                                                                6,
                                                            )
                                                        }
                                                    >
                                                        + Media docena
                                                    </Button>
                                                    <Button
                                                        type="button"
                                                        size="lg"
                                                        variant="outline"
                                                        className="min-h-11 rounded-full bg-public-earth px-2 text-public-ink hover:bg-white"
                                                        onClick={() =>
                                                            addProduct(
                                                                product.slug,
                                                                12,
                                                            )
                                                        }
                                                    >
                                                        + Docena
                                                    </Button>
                                                </div>
                                            </div>
                                        </div>
                                    </article>
                                );
                            })}
                        </div>
                    </div>
                )}
            </section>
            {count > 0 && (
                <div className="fixed inset-x-0 bottom-0 z-30 border-t border-white/35 bg-background/95 px-4 pt-3 pb-[max(0.75rem,env(safe-area-inset-bottom))] shadow-[0_-8px_24px_rgb(27_27_27_/_0.24)] backdrop-blur-sm sm:hidden">
                    <Button
                        asChild
                        size="lg"
                        className="min-h-12 w-full rounded-full bg-public-ink text-base text-white hover:bg-public-ink/90"
                    >
                        <Link href="/cart" data-testid="mobile-cart-cta">
                            <ShoppingBag aria-hidden="true" /> Ver carrito (
                            {count})
                            <ArrowRight
                                className="ml-auto"
                                aria-hidden="true"
                            />
                        </Link>
                    </Button>
                </div>
            )}
        </>
    );
}
