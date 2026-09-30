import { Head, Link } from '@inertiajs/react';
import { Minus, Plus, ShoppingBag } from 'lucide-react';
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
                data-testid="menu-hero"
                aria-labelledby="menu-title"
                className="relative isolate overflow-hidden bg-public-canvas text-white"
            >
                <img
                    data-testid="menu-hero-artwork"
                    src="/images/brand/bg-empanada.png"
                    alt=""
                    aria-hidden="true"
                    className="pointer-events-none absolute top-40 right-[-8rem] z-0 h-[20rem] w-[20rem] max-w-none object-contain opacity-[0.32] select-none sm:top-[-3rem] sm:right-[-14rem] sm:h-[24rem] sm:w-[24rem] lg:top-[-9rem] lg:right-[-10rem] lg:h-[36rem] lg:w-[36rem] lg:opacity-50"
                />
                <header className="relative z-10 mx-auto flex min-h-[17rem] max-w-7xl flex-col justify-center gap-5 px-4 py-7 sm:min-h-[16rem] sm:flex-row sm:items-end sm:justify-between sm:px-8 sm:py-7 lg:min-h-[15rem] lg:px-12">
                    <div className="max-w-3xl sm:max-w-[28rem] lg:max-w-none">
                        <h1
                            id="menu-title"
                            className="font-serif text-5xl leading-[0.95] font-bold tracking-tight sm:text-6xl lg:text-7xl lg:whitespace-nowrap xl:text-8xl"
                        >
                            Nuestro menú.
                        </h1>
                        <p className="mt-4 max-w-xl text-sm leading-6 text-white sm:text-base">
                            Elegí tus empanadas y sumá la cantidad que quieras.
                            El precio final se confirma al revisar el carrito.
                        </p>
                    </div>
                    <Button
                        asChild
                        size="lg"
                        data-testid="menu-hero-cart-cta"
                        className="relative z-10 min-h-12 w-full shrink-0 rounded-full bg-public-earth px-6 text-base text-public-ink transition-colors hover:bg-public-earth-ink hover:text-white focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-white sm:w-fit"
                    >
                        <Link href="/cart">
                            <ShoppingBag aria-hidden="true" /> Ver carrito
                            {count > 0 && ` (${count})`}
                        </Link>
                    </Button>
                </header>
            </section>
            {!loading && !error && products.length > 0 && (
                <div
                    data-testid="menu-category-strip"
                    className="sticky top-0 z-20 w-full border-b border-border bg-public-earth/95 backdrop-blur-sm"
                >
                    <div className="mx-auto max-w-7xl px-4 py-3 sm:px-8 lg:px-12">
                        <div
                            role="group"
                            aria-label="Categorías del menú"
                            className="flex gap-2 overflow-x-auto pb-1"
                        >
                            <Button
                                type="button"
                                size="sm"
                                variant="outline"
                                aria-pressed={category === 'all'}
                                className="min-h-11 shrink-0 rounded-full border-public-ink/20 bg-white px-5 text-public-ink shadow-none transition-colors hover:bg-public-earth-ink hover:text-white focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-public-ink aria-[pressed=true]:border-public-canvas aria-[pressed=true]:bg-public-canvas aria-[pressed=true]:text-white aria-[pressed=true]:hover:bg-public-earth-ink"
                                onClick={() => setCategory('all')}
                            >
                                Todas
                            </Button>
                            {categories.map((item) => (
                                <Button
                                    key={item.slug}
                                    type="button"
                                    size="sm"
                                    variant="outline"
                                    aria-pressed={category === item.slug}
                                    className="min-h-11 shrink-0 rounded-full border-public-ink/20 bg-white px-5 text-public-ink shadow-none transition-colors hover:bg-public-earth-ink hover:text-white focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-public-ink aria-[pressed=true]:border-public-canvas aria-[pressed=true]:bg-public-canvas aria-[pressed=true]:text-white aria-[pressed=true]:hover:bg-public-earth-ink"
                                    onClick={() => setCategory(item.slug)}
                                >
                                    {item.name}
                                </Button>
                            ))}
                        </div>
                    </div>
                </div>
            )}
            <section
                className={`mx-auto max-w-7xl px-4 text-public-ink sm:px-8 lg:px-12 ${count > 0 ? 'pb-32 sm:pb-24' : 'pb-14 sm:pb-18'}`}
            >
                {loading ? (
                    <p
                        role="status"
                        aria-busy="true"
                        className="py-24 text-center text-muted-foreground"
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
                        <p className="mt-3 text-muted-foreground">
                            Volvé a revisar el menú más tarde.
                        </p>
                    </div>
                ) : (
                    <div className="pt-3 sm:pt-4">
                        <p
                            className="mb-4 text-sm font-medium text-public-ink"
                            aria-live="polite"
                        >
                            {category === 'all'
                                ? 'Todo el menú'
                                : categories.find(
                                      (item) => item.slug === category,
                                  )?.name}
                            <span className="ml-2 text-public-earth-ink">
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
                                        className="public-menu-card flex min-w-0 flex-col overflow-hidden rounded-2xl border border-border bg-card text-card-foreground shadow-sm"
                                    >
                                        <div
                                            data-testid="public-product-media"
                                            className="relative aspect-[8/5] overflow-hidden bg-secondary"
                                        >
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
                                                    className="absolute top-4 right-4 rounded-full bg-public-earth px-3 py-1.5 text-xs font-semibold text-public-ink shadow-sm"
                                                    aria-live="polite"
                                                >
                                                    {quantity} en el carrito
                                                </span>
                                            )}
                                        </div>
                                        <div className="flex flex-1 flex-col p-5 sm:p-6">
                                            <p className="text-xs font-bold tracking-[0.16em] text-white uppercase">
                                                {product.category.name}
                                            </p>
                                            <h2 className="mt-2 font-serif text-3xl leading-none font-bold tracking-tight">
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
                                                    <div className="mt-5 flex items-center justify-between gap-3 rounded-xl bg-public-earth px-3 py-2 text-public-ink">
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
                                                            className="size-11 shrink-0 rounded-full bg-white text-public-ink transition-colors hover:bg-public-earth-ink hover:text-white focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-public-earth-ink"
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
                                                        className="col-span-2 min-h-11 justify-start px-0 text-white underline-offset-4 transition-colors hover:text-white hover:underline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-white"
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
                                                        className="col-span-2 min-h-11 rounded-full bg-public-earth text-public-ink transition-colors hover:bg-public-earth-ink hover:text-white focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-white"
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
                                                        className="min-h-11 rounded-full border-white/70 bg-public-earth px-2 text-public-ink transition-colors hover:bg-public-earth-ink hover:text-white focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-white"
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
                                                        className="min-h-11 rounded-full border-white/70 bg-public-earth px-2 text-public-ink transition-colors hover:bg-public-earth-ink hover:text-white focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-white"
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
        </>
    );
}
