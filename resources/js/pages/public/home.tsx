import { Head, Link } from '@inertiajs/react';
import { ArrowRight, Clock3, MessageCircle } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';

export default function Home() {
    return (
        <>
            <Head title="Home" />
            <section className="overflow-hidden border-b">
                <div className="mx-auto grid max-w-6xl items-center gap-10 px-4 py-16 sm:px-6 lg:grid-cols-[1.2fr_0.8fr] lg:py-24">
                    <div className="flex flex-col items-start gap-6">
                        <p className="text-xs font-bold tracking-[0.22em] text-primary uppercase">
                            Handmade empanadas
                        </p>
                        <h1 className="max-w-3xl font-display text-5xl leading-[0.95] font-black tracking-tight sm:text-7xl">
                            Your next order starts here.
                        </h1>
                        <p className="max-w-xl text-lg text-muted-foreground">
                            Browse the menu, build your cart, and send the
                            complete order through WhatsApp.
                        </p>
                        <Button size="lg" asChild>
                            <Link href="/menu">
                                Place an order{' '}
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
            <section className="mx-auto grid max-w-6xl gap-4 px-4 py-14 sm:grid-cols-2 sm:px-6">
                <Card>
                    <CardHeader>
                        <MessageCircle />
                        <CardTitle>Simple confirmation</CardTitle>
                    </CardHeader>
                    <CardContent className="text-sm text-muted-foreground">
                        No online payment: you review the order and send it
                        through WhatsApp.
                    </CardContent>
                </Card>
                <Card>
                    <CardHeader>
                        <Clock3 />
                        <CardTitle>Useful information</CardTitle>
                    </CardHeader>
                    <CardContent className="text-sm text-muted-foreground">
                        Service hours and contact details will be configured in
                        the next product stage.
                    </CardContent>
                </Card>
            </section>
        </>
    );
}
