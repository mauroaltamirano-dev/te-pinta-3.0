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
            <article className="mx-auto max-w-7xl px-4 pt-7 pb-32 sm:px-8 sm:pt-12 sm:pb-20 lg:px-12">
                <nav
                    className="mb-7 flex items-center justify-between gap-4"
                    aria-label="Producto"
                >
                    <Link
                        href="/menu"
                        className="inline-flex min-h-11 items-center gap-2 text-sm font-semibold text-white underline-offset-4 hover:underline"
                    >
                        <ArrowLeft className="size-4" aria-hidden="true" />{' '}
                        Volver al menú
                    </Link>
                    <Button
                        type="button"
                        variant="outline"
                        className="min-h-11 rounded-full bg-public-ink text-white hover:bg-public-ink/90"
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
                    <div className="aspect-[4/3] overflow-hidden rounded-[2rem] bg-secondary lg:aspect-square">
                        {product.image_url ? (
                            <img
                                src={product.image_url}
                                alt={product.name}
                                className="size-full object-cover"
                            />
                        ) : (
                            <div
                                className="relative grid size-full place-items-center overflow-hidden text-public-earth-ink"
                                aria-hidden="true"
                            >
                                <span className="absolute size-[75%] rounded-full border border-primary/25" />
                                <span className="absolute size-[55%] rounded-full border border-primary/35" />
                                <span className="relative font-display text-8xl font-black">
                                    {product.name
                                        .charAt(0)
                                        .toLocaleUpperCase('es-AR')}
                                </span>
                            </div>
                        )}
                    </div>
                    <div className="min-w-0 lg:pt-4">
                        <p className="text-xs font-bold tracking-[0.2em] text-white uppercase">
                            {product.category.name}
                        </p>
                        <h1 className="mt-3 font-display text-5xl leading-none font-black tracking-tight sm:text-6xl">
                            {product.name}
                            <span className="text-public-earth">.</span>
                        </h1>
                        {product.tags.length > 0 && (
                            <div className="mt-5 flex flex-wrap gap-2">
                                {product.tags.map((tag) => (
                                    <span
                                        key={tag.slug}
                                        className="rounded-full border border-white/35 px-3 py-1.5 text-xs font-semibold"
                                    >
                                        {tag.name}
                                    </span>
                                ))}
                            </div>
                        )}
                        {product.description && (
                            <p className="mt-6 text-lg leading-7">
                                {product.description}
                            </p>
                        )}
                        {product.long_description && (
                            <p className="mt-4 text-sm leading-7 whitespace-pre-line text-white">
                                {product.long_description}
                            </p>
                        )}
                        {product.public_ingredients && (
                            <section className="mt-7 border-t border-white/35 pt-6">
                                <h2 className="font-display text-xl font-bold">
                                    Ingredientes
                                </h2>
                                <p className="mt-2 text-sm leading-6 whitespace-pre-line text-white">
                                    {product.public_ingredients}
                                </p>
                            </section>
                        )}
                        <section
                            className="mt-7 border-t border-white/35 pt-6"
                            aria-label="Precios cocinados"
                        >
                            <h2 className="text-xs font-bold tracking-[0.16em] text-white uppercase">
                                Precios cocinados
                            </h2>
                            <dl className="mt-4 grid grid-cols-3 gap-2 text-sm">
                                <div>
                                    <dt className="text-white">Unidad</dt>
                                    <dd className="mt-1 font-bold tabular-nums">
                                        {money.format(product.prices.unit)}
                                    </dd>
                                </div>
                                <div>
                                    <dt className="text-white">Media docena</dt>
                                    <dd className="mt-1 font-bold tabular-nums">
                                        {money.format(
                                            product.prices.half_dozen,
                                        )}
                                    </dd>
                                </div>
                                <div>
                                    <dt className="text-white">Docena</dt>
                                    <dd className="mt-1 font-bold tabular-nums">
                                        {money.format(product.prices.dozen)}
                                    </dd>
                                </div>
                            </dl>
                            <p className="mt-3 text-xs text-white">
                                El total se calcula y confirma al revisar el
                                carrito.
                            </p>
                        </section>
                        {product.is_available ? (
                            <section
                                className="mt-7 border-t border-white/35 pt-6"
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
                                            className="size-11 rounded-full bg-public-ink text-white hover:bg-public-ink/90 disabled:opacity-70"
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
                                            className="size-11 rounded-full bg-public-ink text-white hover:bg-public-ink/90 disabled:opacity-70"
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
                                    className="mt-5 hidden min-h-12 w-full rounded-full bg-public-ink text-white hover:bg-public-ink/90 sm:flex"
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
                                            className="min-h-11 rounded-full bg-public-ink text-white hover:bg-public-ink/90"
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
                            className="mt-5 min-h-11 w-full rounded-full bg-public-earth text-public-ink hover:bg-white"
                        >
                            <Link href="/cart">Ver carrito</Link>
                        </Button>
                    </div>
                </div>
            </article>
            {product.is_available && (
                <div className="fixed inset-x-0 bottom-0 z-30 border-t border-white/35 bg-background/95 px-4 pt-3 pb-[max(0.75rem,env(safe-area-inset-bottom))] shadow-[0_-8px_24px_rgb(27_27_27_/_0.24)] backdrop-blur-sm sm:hidden">
                    <Button
                        type="button"
                        size="lg"
                        className="min-h-12 w-full rounded-full bg-public-ink text-white hover:bg-public-ink/90"
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
