import { Link, usePage } from '@inertiajs/react';
import { ArrowUpRight, ShoppingBag } from 'lucide-react';
import type { PropsWithChildren } from 'react';
import { BrandBadge } from '@/components/brand-badge';
import { CartProvider, useCart } from '@/features/orders/cart-context';
import { useRestaurantBranding } from '@/hooks/use-restaurant-branding';

const navigation = [
    { label: 'Inicio', href: '/' },
    { label: 'Menú', href: '/menu' },
];

export default function PublicLayout({ children }: PropsWithChildren) {
    useRestaurantBranding();

    return (
        <CartProvider>
            <PublicShell>{children}</PublicShell>
        </CartProvider>
    );
}

function PublicShell({ children }: PropsWithChildren) {
    const { url } = usePage();
    const { count } = useCart();
    const pathname = url.split('?')[0];
    const showFloatingCart = count > 0 && pathname !== '/cart';

    return (
        <div className="public-theme public-shell flex min-h-screen flex-col bg-background font-sans text-foreground">
            <a
                href="#main-content"
                className="sr-only rounded-md bg-primary px-4 py-2 text-primary-foreground focus:not-sr-only focus:absolute focus:z-50 focus:m-3"
            >
                Ir al contenido
            </a>
            <header className="relative z-30 bg-public-canvas text-white">
                <nav
                    className="mx-auto flex min-h-16 max-w-7xl items-center justify-between gap-3 px-4 sm:px-8 lg:min-h-20 lg:px-12"
                    aria-label="Navegación pública"
                >
                    <Link
                        href="/"
                        className="shrink-0 rounded-md outline-none focus-visible:ring-2 focus-visible:ring-white"
                        aria-label="Te Pinta, inicio"
                    >
                        <img
                            src="/images/brand/logo-completo-blanco.png"
                            alt=""
                            width="120"
                            height="80"
                            className="h-11 w-auto max-w-28 object-contain sm:h-13 sm:max-w-36"
                        />
                    </Link>
                    <div className="flex items-center gap-1 sm:gap-2">
                        {navigation.map((item) => (
                            <Link
                                key={item.href}
                                href={item.href}
                                aria-current={
                                    pathname === item.href ? 'page' : undefined
                                }
                                className="relative inline-flex min-h-11 items-center px-3 text-sm font-semibold tracking-wide text-white transition-colors after:absolute after:inset-x-3 after:bottom-2 after:h-0.5 after:scale-x-0 after:rounded-full after:bg-white after:transition-transform hover:after:scale-x-100 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-white aria-[current=page]:after:scale-x-100 sm:px-4 sm:after:inset-x-4"
                            >
                                {item.label}
                            </Link>
                        ))}
                    </div>
                </nav>
            </header>
            <main id="main-content" className="flex-1">
                {children}
            </main>
            {showFloatingCart && (
                <Link
                    href="/cart"
                    data-testid="public-cart-fab"
                    aria-label={`Carrito, ${count} ${count === 1 ? 'producto' : 'productos'}`}
                    className="fixed right-[max(1rem,env(safe-area-inset-right))] bottom-[max(1rem,env(safe-area-inset-bottom))] z-40 inline-flex size-14 items-center justify-center rounded-full bg-public-earth-ink text-white shadow-lg transition-colors hover:bg-public-canvas focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-public-ink sm:right-6 sm:bottom-6"
                >
                    <ShoppingBag className="size-6" aria-hidden="true" />
                    <span
                        aria-hidden="true"
                        className="absolute -top-2 -right-2 grid h-6 min-w-6 place-items-center rounded-full bg-public-earth px-1 text-xs font-bold text-public-ink tabular-nums shadow-sm"
                    >
                        {count}
                    </span>
                </Link>
            )}
            <footer className="bg-public-ink text-white">
                <div
                    className={`mx-auto grid max-w-7xl gap-8 px-4 pt-12 sm:px-8 md:grid-cols-[1.4fr_1fr_1fr] lg:px-12 ${showFloatingCart ? 'pb-28' : 'pb-12'}`}
                >
                    <div className="flex items-start gap-4">
                        <BrandBadge
                            title=""
                            aria-hidden="true"
                            className="size-16 shrink-0 text-white"
                        />
                        <div>
                            <p className="font-serif text-2xl font-semibold">
                                Te Pinta
                            </p>
                            <p className="mt-2 max-w-xs text-sm leading-6 text-white/70">
                                Elegí del menú, armá tu pedido y confirmalo por
                                WhatsApp.
                            </p>
                        </div>
                    </div>
                    <div>
                        <p className="text-xs font-semibold tracking-[0.16em] text-white/55 uppercase">
                            Qué ofrecemos
                        </p>
                        <ul className="mt-3 space-y-1.5 text-sm text-white/80">
                            <li>Empanadas, pizzas y más</li>
                            <li>Comé en el local</li>
                            <li>Retiro o envío a domicilio</li>
                            <li>Confirmación por WhatsApp</li>
                        </ul>
                    </div>
                    <nav aria-label="Enlaces del pie">
                        <p className="text-xs font-semibold tracking-[0.16em] text-white/55 uppercase">
                            Explorar
                        </p>
                        <ul className="mt-1 text-sm font-semibold">
                            {[
                                { label: 'Ver menú', href: '/menu' },
                                { label: 'Mi carrito', href: '/cart' },
                            ].map((item) => (
                                <li key={item.href}>
                                    <Link
                                        href={item.href}
                                        className="inline-flex min-h-11 items-center gap-1 hover:text-white/75 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-white"
                                    >
                                        {item.label}
                                        <ArrowUpRight
                                            className="size-4"
                                            aria-hidden="true"
                                        />
                                    </Link>
                                </li>
                            ))}
                        </ul>
                    </nav>
                </div>
                <div className="border-t border-white/10">
                    <p className="mx-auto max-w-7xl px-4 py-5 text-xs text-white/50 sm:px-8 lg:px-12">
                        © {new Date().getFullYear()} Te Pinta. Los precios se
                        confirman al revisar el carrito.
                    </p>
                </div>
            </footer>
        </div>
    );
}
