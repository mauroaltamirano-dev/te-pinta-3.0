import { Head, Link } from '@inertiajs/react';
import {
    ArrowDownRight,
    ArrowRight,
    ArrowUpRight,
    ShoppingBag,
} from 'lucide-react';
import { Button } from '@/components/ui/button';
import { FeaturedProductCard } from '@/features/marketing/components/featured-product-card';
import type { FeaturedProductPublicView } from '@/features/marketing/types';
import { useCart } from '@/features/orders/cart-context';

export default function Home({
    featuredProduct = null,
}: {
    featuredProduct?: FeaturedProductPublicView | null;
}) {
    const { addProduct } = useCart();

    return (
        <>
            <Head title="Empanadas para compartir" />
            <section className="overflow-hidden border-b border-border/70">
                <div className="mx-auto grid max-w-7xl gap-10 px-4 pt-12 pb-14 sm:px-8 sm:pt-16 lg:min-h-165 lg:grid-cols-[1.06fr_0.94fr] lg:items-center lg:gap-16 lg:px-12 lg:py-18">
                    <div className="relative z-1 flex flex-col items-start">
                        <p className="inline-flex items-center gap-3 text-xs font-bold tracking-[0.24em] text-public-earth-ink uppercase">
                            <span
                                className="h-px w-8 bg-primary"
                                aria-hidden="true"
                            />
                            Te Pinta · Empanadas
                        </p>
                        <h1 className="mt-8 max-w-3xl font-display text-[clamp(3.7rem,8vw,8rem)] leading-[0.9] font-black tracking-[-0.055em] text-balance">
                            Se te pinta{' '}
                            <span className="text-primary">una</span> empanada.
                        </h1>
                        <p className="mt-8 max-w-lg text-base leading-7 text-muted-foreground sm:text-lg">
                            Elegí tus favoritas, armá el carrito y terminá tu
                            pedido por WhatsApp. Así de simple.
                        </p>
                        <Button
                            asChild
                            size="lg"
                            className="mt-9 min-h-13 rounded-full px-7 text-base font-semibold shadow-none"
                        >
                            <Link href="/menu">
                                Hacer pedido{' '}
                                <ArrowUpRight
                                    className="size-5"
                                    aria-hidden="true"
                                />
                            </Link>
                        </Button>
                        <p className="mt-5 text-sm text-muted-foreground">
                            Explorá el menú antes de decidir.
                        </p>
                    </div>
                    <div className="relative mx-auto w-full max-w-xl lg:max-w-none">
                        <div className="relative aspect-[5/4] overflow-hidden rounded-[2rem] bg-primary sm:aspect-square sm:rounded-[2.5rem]">
                            {featuredProduct?.product.image_url ? (
                                <img
                                    src={featuredProduct.product.image_url}
                                    alt={featuredProduct.product.name}
                                    className="size-full object-cover"
                                />
                            ) : (
                                <div
                                    className="relative grid size-full place-items-center overflow-hidden text-white"
                                    aria-hidden="true"
                                >
                                    <span className="absolute -top-[18%] -right-[20%] size-[70%] rounded-full border border-white/25" />
                                    <span className="absolute -bottom-[24%] -left-[24%] size-[75%] rounded-full border border-white/25" />
                                    <span className="font-display text-[clamp(7rem,22vw,15rem)] leading-none font-black tracking-[-0.12em] opacity-95">
                                        TP
                                        <span className="text-foreground">
                                            .
                                        </span>
                                    </span>
                                </div>
                            )}
                        </div>
                        <div className="absolute right-4 bottom-4 left-4 flex items-center justify-between gap-3 rounded-2xl bg-background/95 px-5 py-4 shadow-lg backdrop-blur-sm sm:right-6 sm:bottom-6 sm:left-6 sm:px-6">
                            <div>
                                <p className="text-[0.65rem] font-bold tracking-[0.18em] text-public-earth-ink uppercase">
                                    Tu pedido empieza acá
                                </p>
                                <p className="mt-1 font-display text-lg font-bold sm:text-xl">
                                    Elegí lo que se te pinta.
                                </p>
                            </div>
                            <ArrowDownRight
                                className="size-7 shrink-0 text-primary"
                                aria-hidden="true"
                            />
                        </div>
                    </div>
                </div>
            </section>

            <section
                className="border-b border-border/70"
                aria-labelledby="steps-title"
            >
                <div className="mx-auto grid max-w-7xl gap-8 px-4 py-12 sm:grid-cols-[minmax(0,0.85fr)_minmax(0,1.15fr)] sm:items-start sm:px-8 lg:gap-20 lg:px-12 lg:py-18">
                    <div>
                        <p className="text-xs font-bold tracking-[0.2em] text-public-earth-ink uppercase">
                            Cómo pedir
                        </p>
                        <h2
                            id="steps-title"
                            className="mt-3 max-w-xs font-display text-4xl leading-none font-black tracking-tight sm:text-5xl"
                        >
                            Un pedido, tres pasos.
                        </h2>
                    </div>
                    <ol className="grid gap-5 sm:grid-cols-3 sm:gap-4">
                        {[
                            {
                                number: '01',
                                title: 'Elegí',
                                detail: 'Recorré el menú y encontrá tus favoritas.',
                            },
                            {
                                number: '02',
                                title: 'Armá',
                                detail: 'Sumalas al carrito en la cantidad que quieras.',
                            },
                            {
                                number: '03',
                                title: 'Enviá',
                                detail: 'Revisá tu pedido y finalizalo por WhatsApp.',
                            },
                        ].map((step) => (
                            <li
                                key={step.number}
                                className="border-t border-foreground pt-4"
                            >
                                <span className="text-xs font-bold tracking-widest text-public-earth-ink">
                                    {step.number}
                                </span>
                                <h3 className="mt-5 font-display text-2xl font-bold">
                                    {step.title}
                                </h3>
                                <p className="mt-2 text-sm leading-6 text-muted-foreground">
                                    {step.detail}
                                </p>
                            </li>
                        ))}
                    </ol>
                </div>
            </section>

            {featuredProduct && (
                <section
                    className="mx-auto max-w-7xl px-4 py-14 sm:px-8 sm:py-20 lg:px-12"
                    aria-labelledby="featured-title"
                >
                    <div className="mb-7 flex flex-wrap items-end justify-between gap-4 sm:mb-9">
                        <div>
                            <p className="text-xs font-bold tracking-[0.2em] text-public-earth-ink uppercase">
                                Del menú
                            </p>
                            <h2
                                id="featured-title"
                                className="mt-2 font-display text-4xl font-black tracking-tight sm:text-5xl"
                            >
                                Nuestra recomendación.
                            </h2>
                        </div>
                        <Link
                            href="/menu"
                            className="inline-flex min-h-11 items-center gap-2 font-semibold text-public-earth-ink underline-offset-4 hover:underline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring"
                        >
                            Ver todo el menú{' '}
                            <ArrowRight className="size-4" aria-hidden="true" />
                        </Link>
                    </div>
                    <FeaturedProductCard
                        featured={featuredProduct}
                        onAddProduct={(slug) => addProduct(slug, 1)}
                    />
                </section>
            )}

            <section className="mx-auto max-w-7xl px-4 pb-12 sm:px-8 sm:pb-20 lg:px-12">
                <div className="grid gap-8 rounded-[2rem] bg-primary px-6 py-10 text-white sm:px-10 sm:py-14 lg:grid-cols-[1fr_auto] lg:items-end lg:px-14">
                    <div>
                        <p className="text-xs font-bold tracking-[0.2em] uppercase opacity-85">
                            ¿Ya elegiste?
                        </p>
                        <h2 className="mt-3 max-w-2xl font-display text-4xl leading-none font-black tracking-tight text-balance sm:text-6xl">
                            Lo rico está a un pedido de distancia.
                        </h2>
                    </div>
                    <Button
                        asChild
                        size="lg"
                        className="min-h-13 w-fit rounded-full bg-white px-7 text-base font-semibold text-primary shadow-none hover:bg-background"
                    >
                        <Link href="/menu">
                            <ShoppingBag
                                className="size-5"
                                aria-hidden="true"
                            />{' '}
                            Ir al menú
                        </Link>
                    </Button>
                </div>
            </section>
        </>
    );
}
