import { Head, Link } from '@inertiajs/react';
import { Minus, Plus, ShoppingBag } from 'lucide-react';
import { useEffect, useMemo, useState } from 'react';
import { Button } from '@/components/ui/button';
import { useCart } from '@/features/orders/cart-context';
import type { PublicProduct } from '@/features/orders/types';
import { ProductImagePlaceholder } from '@/features/products/components/product-image-placeholder';
import {
    filterBySubcategory,
    subcategoriesOf,
} from '@/features/products/subcategory-filter';

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
    const [subcategory, setSubcategory] = useState('all');
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
    const inCategory =
        category === 'all'
            ? products
            : products.filter((product) => product.category.slug === category);
    // Subcategories narrow one category; "Todas" offers none.
    const subcategories =
        category === 'all' ? [] : subcategoriesOf(inCategory, subcategoryOf);
    const visible = filterBySubcategory(inCategory, subcategoryOf, subcategory);
    // The carta: one section per category, and inside it one group per subcategory (products without one first).
    const sections = (
        category === 'all'
            ? categories
            : categories.filter((item) => item.slug === category)
    ).map((item) => {
        const sectionProducts = visible.filter(
            (product) => product.category.slug === item.slug,
        );
        const groups = [
            {
                key: '',
                name: '',
                products: sectionProducts.filter(
                    (product) => product.subcategory === null,
                ),
            },
            ...subcategoriesOf(sectionProducts, subcategoryOf).map(
                (option) => ({
                    key: option.key,
                    name: option.name,
                    products: sectionProducts.filter(
                        (product) => product.subcategory?.slug === option.key,
                    ),
                }),
            ),
        ].filter((group) => group.products.length > 0);

        return { ...item, count: sectionProducts.length, groups };
    });
    const quantityOf = (slug: string) =>
        items.find((line) => line.product_slug === slug)?.quantity ?? 0;
    const selectCategory = (slug: string) => {
        setCategory(slug);
        setSubcategory('all');
    };

    return (
        <>
            <Head title="Menú" />
            <section
                data-testid="menu-hero"
                aria-labelledby="menu-title"
                className="relative isolate overflow-hidden bg-public-ink text-white"
            >
                <img
                    data-testid="menu-hero-artwork"
                    src="/images/brand/bg-empanada.png"
                    alt=""
                    aria-hidden="true"
                    className="pointer-events-none absolute top-1/2 right-[-6rem] -z-10 h-[22rem] w-[22rem] max-w-none -translate-y-1/2 object-contain opacity-30 select-none sm:right-[-4rem] lg:right-[2%] lg:h-[26rem] lg:w-[26rem]"
                />
                <header className="mx-auto flex max-w-7xl flex-col gap-6 px-4 py-10 sm:flex-row sm:items-end sm:justify-between sm:px-8 sm:py-12 lg:px-12">
                    <div className="max-w-xl">
                        <p className="text-xs font-bold tracking-[0.2em] text-white/70 uppercase">
                            La carta
                        </p>
                        <h1
                            id="menu-title"
                            className="mt-3 font-serif text-5xl leading-none font-semibold tracking-tight sm:text-6xl"
                        >
                            Nuestro menú
                        </h1>
                        <p className="mt-4 text-sm leading-6 text-white/80 sm:text-base">
                            Precios de productos cocinados. Sumá por docena,
                            media docena o unidad; el total final se confirma al
                            revisar el carrito.
                        </p>
                    </div>
                    <Button
                        asChild
                        size="lg"
                        data-testid="menu-hero-cart-cta"
                        className="min-h-12 w-full shrink-0 rounded-full bg-public-earth px-6 text-base text-public-ink transition-colors hover:bg-public-earth-ink hover:text-white focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-white sm:w-fit"
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
                    className="sticky top-0 z-20 w-full border-b border-border bg-white/95 backdrop-blur-sm"
                >
                    <div className="mx-auto max-w-7xl px-4 py-3 sm:px-8 lg:px-12">
                        <div
                            role="group"
                            aria-label="Categorías del menú"
                            className="flex gap-2 overflow-x-auto pb-1"
                        >
                            {[
                                { slug: 'all', name: 'Todas' },
                                ...categories,
                            ].map((item) => (
                                <button
                                    key={item.slug}
                                    type="button"
                                    aria-pressed={category === item.slug}
                                    className="min-h-11 shrink-0 rounded-full border border-border bg-white px-5 text-sm font-medium text-public-ink transition-colors hover:border-public-earth-ink hover:bg-public-earth-ink hover:text-white focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-public-ink aria-[pressed=true]:border-public-canvas aria-[pressed=true]:bg-public-canvas aria-[pressed=true]:text-white aria-[pressed=true]:hover:border-public-earth-ink aria-[pressed=true]:hover:bg-public-earth-ink"
                                    onClick={() => selectCategory(item.slug)}
                                >
                                    {item.name}
                                </button>
                            ))}
                        </div>
                        {subcategories.length > 0 && (
                            <div
                                role="group"
                                aria-label="Subcategorías del menú"
                                className="mt-2 flex gap-1 overflow-x-auto pb-1"
                            >
                                {[
                                    { key: 'all', name: 'Todas' },
                                    ...subcategories,
                                ].map((item) => (
                                    <button
                                        key={item.key}
                                        type="button"
                                        aria-pressed={subcategory === item.key}
                                        onClick={() => setSubcategory(item.key)}
                                        className="relative min-h-10 shrink-0 px-3 text-sm text-public-ink/70 transition-colors after:absolute after:inset-x-3 after:bottom-1 after:h-0.5 after:rounded-full hover:text-public-ink focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-public-ink aria-[pressed=true]:font-semibold aria-[pressed=true]:text-public-ink aria-[pressed=true]:after:bg-public-canvas"
                                    >
                                        {item.name}
                                    </button>
                                ))}
                            </div>
                        )}
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
                        className="my-10 rounded-xl bg-white py-24 text-center text-destructive"
                    >
                        {error}
                    </p>
                ) : products.length === 0 ? (
                    <div className="mx-auto max-w-md py-24 text-center">
                        <h2 className="font-serif text-3xl font-semibold">
                            Por ahora no hay productos disponibles.
                        </h2>
                        <p className="mt-3 text-muted-foreground">
                            Volvé a revisar el menú más tarde.
                        </p>
                    </div>
                ) : (
                    <div className="pt-6 sm:pt-8">
                        <p className="sr-only" aria-live="polite">
                            {category === 'all'
                                ? 'Todo el menú'
                                : [
                                      categories.find(
                                          (item) => item.slug === category,
                                      )?.name,
                                      subcategories.find(
                                          (item) => item.key === subcategory,
                                      )?.name,
                                  ]
                                      .filter(Boolean)
                                      .join(' · ')}
                            : {visible.length}{' '}
                            {visible.length === 1 ? 'opción' : 'opciones'}
                        </p>
                        {sections.map((section) => (
                            <section
                                key={section.slug}
                                aria-labelledby={`menu-section-${section.slug}`}
                                className="mb-12 last:mb-0"
                            >
                                <div className="mb-5 flex items-baseline justify-between gap-4 border-b border-public-ink pb-3">
                                    <h2
                                        id={`menu-section-${section.slug}`}
                                        className="font-serif text-3xl font-semibold tracking-tight sm:text-4xl"
                                    >
                                        {section.name}
                                    </h2>
                                    <span className="text-sm text-muted-foreground tabular-nums">
                                        {section.count}{' '}
                                        {section.count === 1
                                            ? 'opción'
                                            : 'opciones'}
                                    </span>
                                </div>
                                {section.groups.map((group) => (
                                    <div
                                        key={group.key || 'none'}
                                        className="mb-8 last:mb-0"
                                    >
                                        {group.name !== '' && (
                                            <h3 className="mb-4 text-xs font-bold tracking-[0.18em] text-public-earth-ink uppercase">
                                                {group.name}
                                            </h3>
                                        )}
                                        <div className="grid gap-5 sm:grid-cols-2 lg:grid-cols-3 lg:gap-6">
                                            {group.products.map((product) => (
                                                <MenuProductCard
                                                    key={product.slug}
                                                    product={product}
                                                    quantity={quantityOf(
                                                        product.slug,
                                                    )}
                                                    onAdd={(units) =>
                                                        addProduct(
                                                            product.slug,
                                                            units,
                                                        )
                                                    }
                                                    onRemoveOne={() =>
                                                        setProductQuantity(
                                                            product.slug,
                                                            quantityOf(
                                                                product.slug,
                                                            ) - 1,
                                                        )
                                                    }
                                                />
                                            ))}
                                        </div>
                                    </div>
                                ))}
                            </section>
                        ))}
                    </div>
                )}
            </section>
        </>
    );
}

const secondaryAction =
    'min-h-11 rounded-full border-transparent bg-public-earth px-3 text-public-ink hover:bg-public-earth-ink hover:text-white focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-public-ink';

function MenuProductCard({
    product,
    quantity,
    onAdd,
    onRemoveOne,
}: {
    product: PublicProduct;
    quantity: number;
    onAdd: (units: number) => void;
    onRemoveOne: () => void;
}) {
    const isEmpanada = product.pricing_family === 'empanada';
    const detailUrl = `/menu/${encodeURIComponent(product.slug)}`;

    return (
        <article
            data-testid="public-product-card"
            className="flex min-w-0 flex-col overflow-hidden rounded-2xl border border-border bg-card text-card-foreground transition-shadow hover:shadow-lg hover:shadow-public-ink/5"
        >
            <Link
                href={detailUrl}
                tabIndex={-1}
                aria-hidden="true"
                data-testid="public-product-media"
                className="relative block aspect-[16/10] overflow-hidden bg-secondary"
            >
                {product.image_url ? (
                    <img
                        src={product.image_url}
                        alt=""
                        loading="lazy"
                        decoding="async"
                        width="640"
                        height="400"
                        className="size-full object-cover"
                    />
                ) : (
                    <ProductImagePlaceholder />
                )}
                {quantity > 0 && (
                    <span
                        className="absolute top-3 right-3 rounded-full bg-public-earth px-3 py-1.5 text-xs font-semibold text-public-ink shadow-sm"
                        aria-live="polite"
                    >
                        {quantity} en el carrito
                    </span>
                )}
            </Link>
            <div className="flex flex-1 flex-col p-5">
                <p className="text-xs font-semibold tracking-[0.14em] text-public-earth-ink uppercase">
                    <span>{product.category.name}</span>
                    {product.subcategory && (
                        <span className="text-muted-foreground">
                            {' '}
                            · {product.subcategory.name}
                        </span>
                    )}
                </p>
                <h3 className="mt-1.5 font-serif text-2xl leading-tight font-semibold tracking-tight">
                    <Link
                        href={detailUrl}
                        className="underline-offset-4 hover:underline focus-visible:underline focus-visible:outline-none"
                    >
                        {product.name}
                    </Link>
                </h3>
                {product.tags.length > 0 && (
                    <div className="mt-2 flex flex-wrap gap-1.5">
                        {product.tags.map((tag) => (
                            <span
                                key={tag.slug}
                                className="rounded-full bg-secondary px-2.5 py-0.5 text-xs font-medium text-muted-foreground"
                            >
                                {tag.name}
                            </span>
                        ))}
                    </div>
                )}
                {product.description && (
                    <p className="mt-2 line-clamp-2 text-sm leading-6 text-muted-foreground">
                        {product.description}
                    </p>
                )}
                <div className="mt-auto pt-5">
                    <dl className="flex items-end justify-between gap-3 border-t border-border pt-4">
                        <div>
                            <dt className="text-xs text-muted-foreground">
                                {isEmpanada ? 'Docena' : 'Precio'}
                            </dt>
                            <dd className="text-2xl font-semibold tabular-nums">
                                {money.format(
                                    isEmpanada
                                        ? product.prices.dozen!
                                        : product.prices.unit,
                                )}
                            </dd>
                        </div>
                        {isEmpanada && (
                            <div className="text-right text-xs text-muted-foreground">
                                <dt className="sr-only">
                                    Unidad y media docena
                                </dt>
                                <dd className="tabular-nums">
                                    Unidad {money.format(product.prices.unit)}
                                </dd>
                                <dd className="tabular-nums">
                                    Media{' '}
                                    {money.format(product.prices.half_dozen!)}
                                </dd>
                            </div>
                        )}
                    </dl>
                    {quantity > 0 && (
                        <div className="mt-4 flex items-center justify-between gap-3 rounded-xl bg-secondary px-3 py-1.5">
                            <span
                                className="text-sm font-semibold"
                                aria-live="polite"
                            >
                                En tu carrito: {quantity}
                            </span>
                            <Button
                                type="button"
                                size="icon"
                                variant="outline"
                                className="size-11 shrink-0 rounded-full bg-white text-public-ink hover:bg-public-ink hover:text-white focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-public-ink"
                                aria-label={`Quitar una unidad de ${product.name}`}
                                onClick={onRemoveOne}
                            >
                                <Minus aria-hidden="true" />
                            </Button>
                        </div>
                    )}
                    {isEmpanada ? (
                        <div className="mt-4 grid grid-cols-2 gap-2">
                            <Button
                                type="button"
                                className="col-span-2 min-h-11 rounded-full bg-public-canvas text-white hover:bg-public-earth-ink focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-public-ink"
                                onClick={() => onAdd(12)}
                            >
                                + Docena
                            </Button>
                            <Button
                                type="button"
                                variant="outline"
                                className={secondaryAction}
                                onClick={() => onAdd(6)}
                            >
                                + Media docena
                            </Button>
                            <Button
                                type="button"
                                variant="outline"
                                className={secondaryAction}
                                aria-label={`Agregar una unidad de ${product.name}`}
                                onClick={() => onAdd(1)}
                            >
                                Unidad
                            </Button>
                        </div>
                    ) : (
                        <Button
                            type="button"
                            className="mt-4 min-h-11 w-full rounded-full bg-public-canvas text-white hover:bg-public-earth-ink focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-public-ink"
                            aria-label={`Agregar una unidad de ${product.name}`}
                            onClick={() => onAdd(1)}
                        >
                            <Plus aria-hidden="true" /> Agregar
                        </Button>
                    )}
                    <Link
                        href={detailUrl}
                        className="mt-3 inline-flex min-h-11 items-center text-sm font-medium text-public-earth-ink underline-offset-4 hover:underline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-public-ink"
                    >
                        Ver detalles
                    </Link>
                </div>
            </div>
        </article>
    );
}

function subcategoryOf(product: PublicProduct) {
    return (
        product.subcategory && {
            ...product.subcategory,
            key: product.subcategory.slug,
        }
    );
}
