import { Head, Link } from '@inertiajs/react';
import {
    ArrowLeft,
    Check,
    Minus,
    Plus,
    Share2,
    ShoppingBag,
} from 'lucide-react';
import { useEffect, useState } from 'react';
import { Button } from '@/components/ui/button';
import { useCart } from '@/features/orders/cart-context';
import type { PublicProduct } from '@/features/orders/types';
import { DozenPrice } from '@/features/products/components/dozen-price';
import { ProductImagePlaceholder } from '@/features/products/components/product-image-placeholder';
import { VegetarianLeaf } from '@/features/products/components/vegetarian-leaf';
import { isVegetarian } from '@/features/products/utils';

const money = new Intl.NumberFormat('es-AR', {
    style: 'currency',
    currency: 'ARS',
    maximumFractionDigits: 0,
});

interface ProductSeo {
    title: string;
    description: string;
    url: string;
    image: string | null;
}

export default function ProductDetail({
    product,
    seo,
}: {
    product: PublicProduct & { is_available: boolean };
    seo: ProductSeo;
}) {
    const [quantity, setQuantity] = useState(1);
    const [shareState, setShareState] = useState('');
    const { items, addProduct, setProductQuantity } = useCart();
    const inCart =
        items.find((item) => item.product_slug === product.slug)?.quantity ?? 0;

    useEffect(() => {
        document
            .querySelectorAll('[data-public-seo]')
            .forEach((node) => node.remove());
    }, []);

    const share = async () => {
        try {
            if (navigator.share) {
                await navigator.share({ title: seo.title, url: seo.url });

                return;
            }

            await navigator.clipboard.writeText(seo.url);
            setShareState('Enlace copiado');
        } catch {
            setShareState('No se pudo compartir el enlace');
        }
    };

    return (
        <>
            <Head title={seo.title}>
                <meta name="description" content={seo.description} />
                <link rel="canonical" href={seo.url} />
                <meta property="og:type" content="website" />
                <meta property="og:title" content={seo.title} />
                <meta property="og:description" content={seo.description} />
                <meta property="og:url" content={seo.url} />
                {seo.image && <meta property="og:image" content={seo.image} />}
            </Head>
            <article className="mx-auto max-w-7xl px-4 pt-7 pb-32 text-public-ink sm:px-8 sm:pt-12 sm:pb-20 lg:px-12">
                <nav
                    className="mb-7 flex items-center justify-between gap-4"
                    aria-label="Producto"
                >
                    <Link
                        href="/menu"
                        className="inline-flex min-h-11 items-center gap-2 text-sm font-semibold text-public-ink underline-offset-4 hover:underline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-public-ink"
                    >
                        <ArrowLeft className="size-4" aria-hidden="true" />{' '}
                        Volver al menú
                    </Link>
                    <Button
                        type="button"
                        variant="outline"
                        className="min-h-11 rounded-full border-public-ink/25 bg-white text-public-ink hover:border-public-ink hover:bg-white"
                        onClick={share}
                    >
                        <Share2 aria-hidden="true" /> Compartir
                    </Button>
                </nav>
                {shareState && (
                    <p role="status" className="mb-4 text-right text-sm">
                        {shareState}
                    </p>
                )}
                <div className="grid gap-8 lg:grid-cols-[minmax(0,1.15fr)_minmax(22rem,0.85fr)] lg:gap-14">
                    <div className="aspect-[4/3] overflow-hidden rounded-3xl border border-border bg-secondary lg:aspect-square">
                        {product.image_url ? (
                            <img
                                src={product.image_url}
                                alt={product.name}
                                width="960"
                                height="960"
                                fetchPriority="high"
                                className="size-full object-cover"
                            />
                        ) : (
                            <ProductImagePlaceholder />
                        )}
                    </div>
                    <div className="min-w-0 lg:pt-4">
                        <p className="text-xs font-bold tracking-[0.2em] text-public-earth-ink uppercase">
                            {product.category.name}
                            {product.subcategory && (
                                <span className="text-muted-foreground">
                                    {' '}
                                    · {product.subcategory.name}
                                </span>
                            )}
                        </p>
                        <div
                            data-testid="product-detail-title"
                            className="mt-3 flex items-start gap-3"
                        >
                            <h1 className="font-serif text-5xl leading-none font-semibold tracking-tight sm:text-6xl">
                                {product.name}
                                <span className="text-public-canvas">.</span>
                            </h1>
                            {isVegetarian(product.tags) && (
                                <VegetarianLeaf className="mt-1 size-10 sm:size-12" />
                            )}
                        </div>
                        {product.tags.length > 0 && (
                            <div className="mt-5 flex flex-wrap gap-2">
                                {product.tags.map((tag) => (
                                    <span
                                        key={tag.slug}
                                        className="rounded-full bg-secondary px-3 py-1.5 text-xs font-semibold text-muted-foreground"
                                    >
                                        {tag.name}
                                    </span>
                                ))}
                            </div>
                        )}
                        {product.description && (
                            <p className="mt-6 text-lg leading-7 text-public-ink/85">
                                {product.description}
                            </p>
                        )}
                        {product.long_description && (
                            <p className="mt-4 text-sm leading-7 whitespace-pre-line text-muted-foreground">
                                {product.long_description}
                            </p>
                        )}
                        {product.public_ingredients && (
                            <section className="mt-7 border-t border-border pt-6">
                                <h2 className="font-serif text-xl font-semibold">
                                    Ingredientes
                                </h2>
                                <p className="mt-2 text-sm leading-6 whitespace-pre-line text-muted-foreground">
                                    {product.public_ingredients}
                                </p>
                            </section>
                        )}
                        <section
                            className="mt-7 border-t border-border pt-6"
                            aria-label={
                                product.pricing_family === 'empanada'
                                    ? 'Precios'
                                    : 'Precio por unidad'
                            }
                        >
                            <h2 className="text-xs font-bold tracking-[0.16em] text-muted-foreground uppercase">
                                {product.pricing_family === 'empanada'
                                    ? 'Precios'
                                    : 'Precio'}
                            </h2>
                            <dl
                                className={`mt-4 grid gap-2 text-sm ${
                                    product.pricing_family === 'empanada'
                                        ? 'grid-cols-2'
                                        : 'grid-cols-1'
                                }`}
                            >
                                <div>
                                    <dt className="text-muted-foreground">
                                        {product.pricing_family === 'empanada'
                                            ? 'Unidad'
                                            : 'Por unidad'}
                                    </dt>
                                    <dd className="mt-1 text-lg font-semibold tabular-nums">
                                        {money.format(product.prices.unit)}
                                    </dd>
                                </div>
                                {product.pricing_family === 'empanada' && (
                                    <>
                                        <div>
                                            <dt className="text-muted-foreground">
                                                Media docena
                                            </dt>
                                            <dd className="mt-1 font-bold tabular-nums">
                                                {money.format(
                                                    product.prices.half_dozen!,
                                                )}
                                            </dd>
                                        </div>
                                        <div className="col-span-2 rounded-xl bg-secondary px-4 py-3">
                                            <dt className="text-muted-foreground">
                                                Docena
                                            </dt>
                                            <dd className="mt-1 text-2xl font-bold">
                                                <DozenPrice
                                                    unitPrice={
                                                        product.prices.unit
                                                    }
                                                    dozenPrice={
                                                        product.prices.dozen!
                                                    }
                                                />
                                            </dd>
                                        </div>
                                    </>
                                )}
                            </dl>
                            <p className="mt-3 text-xs text-muted-foreground">
                                El total se calcula y confirma al revisar el
                                carrito.
                            </p>
                        </section>
                        {product.is_available ? (
                            <section
                                className="mt-7 border-t border-border pt-6"
                                aria-label="Agregar al carrito"
                            >
                                <div className="flex flex-wrap items-center justify-between gap-4">
                                    <span className="text-sm font-semibold">
                                        Cantidad a agregar
                                    </span>
                                    <div className="flex items-center gap-2">
                                        <Button
                                            type="button"
                                            variant="outline"
                                            size="icon"
                                            className="size-11 rounded-full border-public-ink/25 bg-white text-public-ink hover:border-public-ink hover:bg-white disabled:opacity-50"
                                            aria-label="Disminuir cantidad"
                                            disabled={quantity <= 1}
                                            onClick={() =>
                                                setQuantity((value) =>
                                                    Math.max(1, value - 1),
                                                )
                                            }
                                        >
                                            <Minus aria-hidden="true" />
                                        </Button>
                                        <output
                                            aria-label="Cantidad a agregar"
                                            className="w-8 text-center font-bold tabular-nums"
                                        >
                                            {quantity}
                                        </output>
                                        <Button
                                            type="button"
                                            variant="outline"
                                            size="icon"
                                            className="size-11 rounded-full border-public-ink/25 bg-white text-public-ink hover:border-public-ink hover:bg-white disabled:opacity-50"
                                            aria-label="Aumentar cantidad"
                                            onClick={() =>
                                                setQuantity(
                                                    (value) => value + 1,
                                                )
                                            }
                                        >
                                            <Plus aria-hidden="true" />
                                        </Button>
                                    </div>
                                </div>
                                <Button
                                    type="button"
                                    size="lg"
                                    className="mt-5 hidden min-h-12 w-full rounded-full bg-public-canvas text-white hover:bg-public-earth-ink sm:flex"
                                    onClick={() =>
                                        addProduct(product.slug, quantity)
                                    }
                                >
                                    <ShoppingBag aria-hidden="true" /> Agregar{' '}
                                    {quantity} al carrito
                                </Button>
                                {inCart > 0 && (
                                    <div className="mt-5 flex flex-wrap items-center justify-between gap-3 rounded-xl bg-secondary p-3 text-secondary-foreground">
                                        <span className="text-sm font-semibold">
                                            <Check
                                                className="mr-1 inline size-4"
                                                aria-hidden="true"
                                            />{' '}
                                            {inCart} en tu carrito
                                        </span>
                                        <Button
                                            type="button"
                                            variant="outline"
                                            className="min-h-11 rounded-full border-public-ink/25 bg-white text-public-ink hover:border-public-ink hover:bg-white"
                                            onClick={() =>
                                                setProductQuantity(
                                                    product.slug,
                                                    0,
                                                )
                                            }
                                        >
                                            Quitar del carrito
                                        </Button>
                                    </div>
                                )}
                            </section>
                        ) : (
                            <p
                                role="status"
                                className="mt-7 rounded-xl bg-secondary p-4 font-semibold text-secondary-foreground"
                            >
                                Por ahora no está disponible para pedir.
                            </p>
                        )}
                        <Button
                            asChild
                            variant="outline"
                            className="mt-5 min-h-11 w-full rounded-full border-public-ink/25 bg-white text-public-ink hover:border-public-ink hover:bg-white"
                        >
                            <Link href="/cart">Ver carrito</Link>
                        </Button>
                    </div>
                </div>
            </article>
            {product.is_available && (
                <div className="fixed inset-x-0 bottom-0 z-30 border-t border-border bg-white/95 px-4 pt-3 pb-[max(0.75rem,env(safe-area-inset-bottom))] shadow-[0_-8px_24px_rgb(27_27_27_/_0.08)] backdrop-blur-sm sm:hidden">
                    <Button
                        type="button"
                        size="lg"
                        className="min-h-12 w-full rounded-full bg-public-canvas text-white hover:bg-public-earth-ink"
                        onClick={() => addProduct(product.slug, quantity)}
                    >
                        <ShoppingBag aria-hidden="true" /> Agregar {quantity} al
                        carrito
                    </Button>
                </div>
            )}
        </>
    );
}
