import { Head, Link } from '@inertiajs/react';
import { ArrowRight, MessageCircle, ShoppingBag } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { FeaturedProductCard } from '@/features/marketing/components/featured-product-card';
import type { FeaturedProductPublicView } from '@/features/marketing/types';
import { useCart } from '@/features/orders/cart-context';

const orderSteps = [
    {
        number: '01',
        title: 'Elegí',
        detail: 'Recorré el menú y encontrá tus favoritas.',
        icon: ShoppingBag,
    },
    {
        number: '02',
        title: 'Armá',
        detail: 'Sumalas al carrito en la cantidad que quieras.',
        icon: ArrowRight,
    },
    {
        number: '03',
        title: 'Enviá',
        detail: 'Revisá tu pedido y finalizalo por WhatsApp.',
        icon: MessageCircle,
    },
];

export default function Home({
    featuredProduct = null,
}: {
    featuredProduct?: FeaturedProductPublicView | null;
}) {
    const { addProduct } = useCart();

    return (
        <>
            <Head title="Empanadas para compartir">
                <link rel="preload" as="image" href="/images/brand/hero.png" />
            </Head>

            <section className="relative isolate flex min-h-150 items-center overflow-hidden bg-public-ink text-white sm:min-h-165 lg:min-h-175">
                <img
                    data-testid="public-hero-image"
                    src="/images/brand/hero.png"
                    alt=""
                    fetchPriority="high"
                    className="absolute inset-0 -z-20 size-full object-cover object-[66%_center] sm:object-center"
                />
                <div
                    className="absolute inset-0 -z-10 bg-linear-to-b from-black/85 via-black/60 to-black/15 sm:bg-linear-to-r sm:from-black/85 sm:via-black/60 sm:to-transparent"
                    aria-hidden="true"
                />
                <div className="mx-auto w-full max-w-7xl px-4 py-20 sm:px-8 lg:px-12">
                    <div className="max-w-2xl">
                        <p className="flex items-center gap-3 text-xs font-bold tracking-[0.2em] text-white/90 uppercase">
                            <span
                                className="h-px w-8 bg-public-earth"
                                aria-hidden="true"
                            />
                            Empanadas Te Pinta
                        </p>
                        <h1 className="mt-6 font-serif text-[clamp(3.4rem,7vw,7rem)] leading-[0.93] font-bold tracking-[-0.055em] text-balance">
                            Te pinta{' '}
                            <span className="text-public-earth">una</span>{' '}
                            empanada.
                        </h1>
                        <p className="mt-7 max-w-md text-base leading-7 text-white/90 sm:text-lg">
                            Elegí tus favoritas, armá el carrito y terminá tu
                            pedido por WhatsApp.
                        </p>
                        <Button
                            asChild
                            size="lg"
                            className="mt-8 min-h-13 rounded-full bg-public-canvas px-7 text-base font-semibold text-white shadow-lg shadow-black/25 transition-colors hover:bg-public-earth-ink focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-white"
                        >
                            <Link href="/menu">
                                Hacer pedido
                                <ArrowRight
                                    className="size-5"
                                    aria-hidden="true"
                                />
                            </Link>
                        </Button>
                    </div>
                </div>
            </section>

            <section
                className="bg-public-earth text-public-ink"
                aria-labelledby="home-menu-title"
            >
                <div className="mx-auto grid max-w-7xl gap-8 px-4 py-14 sm:px-8 sm:py-18 lg:grid-cols-[minmax(0,0.45fr)_minmax(0,1fr)] lg:gap-12 lg:px-12">
                    <div className="lg:pt-3">
                        <p className="text-xs font-bold tracking-[0.2em] text-public-earth-ink uppercase">
                            Nuestro menú
                        </p>
                        <h2
                            id="home-menu-title"
                            className="mt-3 max-w-sm font-serif text-4xl leading-[1.02] font-bold tracking-tight sm:text-5xl"
                        >
                            Algo rico para cada antojo.
                        </h2>
                        <p className="mt-5 max-w-sm text-base leading-7 text-muted-foreground">
                            Conocé las variedades disponibles y elegí cómo
                            querés armar tu pedido.
                        </p>
                        <Link
                            href="/menu"
                            className="mt-5 inline-flex min-h-11 items-center gap-2 font-semibold text-public-earth-ink underline-offset-4 hover:underline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring"
                        >
                            Ver menú completo
                            <ArrowRight className="size-4" aria-hidden="true" />
                        </Link>
                    </div>
                    {featuredProduct ? (
                        <FeaturedProductCard
                            featured={featuredProduct}
                            onAddProduct={(slug) => addProduct(slug, 1)}
                            className="shadow-xl shadow-public-ink/8"
                        />
                    ) : (
                        <Link
                            href="/menu"
                            className="group relative flex min-h-60 items-end overflow-hidden rounded-[2rem] bg-public-ink p-7 text-white focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring sm:min-h-80 sm:p-10"
                        >
                            <img
                                src="/images/brand/hero.png"
                                alt=""
                                loading="lazy"
                                className="absolute inset-0 size-full object-cover object-right opacity-65 motion-safe:transition-transform motion-safe:duration-300 motion-safe:group-hover:scale-105"
                            />
                            <span className="absolute inset-0 bg-linear-to-t from-black/80 via-black/20 to-transparent" />
                            <span className="relative flex w-full items-end justify-between gap-4 font-serif text-3xl font-bold sm:text-4xl">
                                Explorá nuestras variedades.
                                <ArrowRight
                                    className="size-7 shrink-0"
                                    aria-hidden="true"
                                />
                            </span>
                        </Link>
                    )}
                </div>
            </section>

            <section className="text-white" aria-labelledby="steps-title">
                <div className="mx-auto grid max-w-7xl gap-9 px-4 py-14 sm:px-8 sm:py-18 lg:grid-cols-[minmax(0,0.45fr)_minmax(0,1fr)] lg:gap-12 lg:px-12">
                    <div>
                        <p className="text-xs font-bold tracking-[0.2em] text-white uppercase">
                            Cómo pedir
                        </p>
                        <h2
                            id="steps-title"
                            className="mt-3 max-w-sm font-serif text-4xl leading-[1.02] font-bold tracking-tight sm:text-5xl"
                        >
                            Un pedido, tres pasos.
                        </h2>
                    </div>
                    <ol className="grid gap-4 sm:grid-cols-3">
                        {orderSteps.map((step) => {
                            const Icon = step.icon;

                            return (
                                <li
                                    key={step.number}
                                    className="rounded-2xl bg-card p-5 text-card-foreground shadow-sm sm:p-6"
                                >
                                    <span className="flex size-12 items-center justify-center rounded-full bg-secondary text-public-earth-ink">
                                        <Icon
                                            className="size-6"
                                            aria-hidden="true"
                                        />
                                    </span>
                                    <span className="mt-6 block text-xs font-bold tracking-widest text-public-earth-ink">
                                        {step.number}
                                    </span>
                                    <h3 className="mt-2 font-serif text-2xl font-bold">
                                        {step.title}
                                    </h3>
                                    <p className="mt-2 text-sm leading-6 text-muted-foreground">
                                        {step.detail}
                                    </p>
                                </li>
                            );
                        })}
                    </ol>
                </div>
            </section>

            <section className="bg-public-ink text-white">
                <div className="mx-auto flex max-w-7xl flex-col gap-7 px-4 py-10 sm:px-8 lg:flex-row lg:items-center lg:justify-between lg:px-12 lg:py-12">
                    <div>
                        <p className="text-xs font-bold tracking-[0.2em] uppercase opacity-85">
                            ¿Ya elegiste?
                        </p>
                        <h2 className="mt-2 max-w-2xl font-serif text-3xl leading-tight font-bold tracking-tight text-balance sm:text-4xl">
                            Lo rico está a un pedido de distancia.
                        </h2>
                    </div>
                    <Button
                        asChild
                        size="lg"
                        className="min-h-13 w-fit shrink-0 rounded-full bg-public-earth px-7 text-base font-semibold text-public-ink shadow-none hover:bg-white"
                    >
                        <Link href="/menu">
                            Ir al menú
                            <ArrowRight className="size-5" aria-hidden="true" />
                        </Link>
                    </Button>
                </div>
            </section>
        </>
    );
}
