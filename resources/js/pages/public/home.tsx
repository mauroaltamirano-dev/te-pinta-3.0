import { Head, Link } from '@inertiajs/react';
import { ArrowRight, Clock3, MessageCircle } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
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
            <Head title="Inicio" />
            <section className="overflow-hidden border-b">
                <div className="mx-auto grid max-w-6xl items-center gap-10 px-4 py-16 sm:px-6 lg:grid-cols-[1.2fr_0.8fr] lg:py-24">
                    <div className="flex flex-col items-start gap-6">
                        <p className="text-xs font-bold tracking-[0.22em] text-primary uppercase">
                            Empanadas artesanales
                        </p>
                        <h1 className="max-w-3xl font-display text-5xl leading-[0.95] font-black tracking-tight sm:text-7xl">
                            Tu próximo pedido empieza acá.
                        </h1>
                        <p className="max-w-xl text-lg text-muted-foreground">
                            Recorré el menú, armá tu carrito y enviá el pedido
                            completo por WhatsApp.
                        </p>
                        <Button size="lg" asChild>
                            <Link href="/menu">
                                Hacer un pedido{' '}
                                <ArrowRight data-icon="inline-end" />
                            </Link>
                        </Button>
                    </div>
                    <div
                        className="food-mark grid aspect-square place-items-center rounded-[2.5rem] border shadow-2xl"
                        aria-hidden="true"
                    >
                        <span className="font-display text-8xl font-black">
                            TP
                        </span>
                    </div>
                </div>
            </section>
            {featuredProduct && (
                <section className="mx-auto max-w-6xl px-4 py-12 sm:px-6 sm:py-16">
                    <FeaturedProductCard
                        featured={featuredProduct}
                        onAddProduct={(slug) => addProduct(slug, 1)}
                    />
                </section>
            )}
            <section className="mx-auto grid max-w-6xl gap-4 px-4 py-14 sm:grid-cols-2 sm:px-6">
                <Card>
                    <CardHeader>
                        <MessageCircle />
                        <CardTitle>Confirmación simple</CardTitle>
                    </CardHeader>
                    <CardContent className="text-sm text-muted-foreground">
                        Sin pago en línea: revisás el pedido y lo enviás por
                        WhatsApp.
                    </CardContent>
                </Card>
                <Card>
                    <CardHeader>
                        <Clock3 />
                        <CardTitle>Información útil</CardTitle>
                    </CardHeader>
                    <CardContent className="text-sm text-muted-foreground">
                        Los horarios de atención y los datos de contacto se
                        configurarán en una próxima etapa.
                    </CardContent>
                </Card>
            </section>
        </>
    );
}
