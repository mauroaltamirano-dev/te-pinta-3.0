import { Link, usePage } from '@inertiajs/react';
import { ArrowUpRight, ShoppingBag } from 'lucide-react';
import type { PropsWithChildren } from 'react';
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
            <header className="relative z-10 bg-background">
                <nav
                    className="mx-auto flex min-h-18 max-w-7xl items-center justify-between gap-3 px-4 sm:px-8 lg:min-h-22 lg:px-12"
                    aria-label="Navegación pública"
                >
                    <Link
                        href="/"
                        className="shrink-0 rounded-md outline-none focus-visible:ring-2 focus-visible:ring-ring"
                        aria-label="Te Pinta, inicio"
                    >
                        <img
                            src="/images/brand/logo-completo-blanco.png"
                            alt=""
                            width="120"
                            height="80"
                            className="h-12 w-auto max-w-32 object-contain sm:h-15 sm:max-w-40"
                        />
                    </Link>
                    <div className="flex items-center gap-1 sm:gap-3">
                        {navigation.map((item) => (
                            <Link
                                key={item.href}
                                href={item.href}
                                aria-current={
                                    pathname === item.href ? 'page' : undefined
                                }
                                className="hidden min-h-11 items-center rounded-full px-4 text-sm font-semibold transition-colors hover:bg-public-earth hover:text-public-ink focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-white aria-[current=page]:bg-public-earth aria-[current=page]:text-public-ink sm:inline-flex"
                            >
                                {item.label}
                            </Link>
                        ))}
                        <Link
                            href="/menu"
                            aria-current={
                                pathname === '/menu' ? 'page' : undefined
                            }
                            className="inline-flex min-h-11 items-center rounded-full px-3 text-sm font-semibold transition-colors hover:bg-public-earth hover:text-public-ink focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-white aria-[current=page]:bg-public-earth aria-[current=page]:text-public-ink sm:hidden"
                        >
                            Menú
                        </Link>
                    </div>
                </nav>
            </header>
            <main
                id="main-content"
                className={`flex-1 ${pathname === '/menu' ? 'bg-public-earth' : ''}`}
            >
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
                    className={`mx-auto grid max-w-7xl gap-8 px-4 pt-10 sm:grid-cols-[1fr_auto] sm:items-end sm:px-8 lg:px-12 ${showFloatingCart ? 'pb-28' : 'pb-10'}`}
                >
                    <div className="flex items-center gap-4">
                        <img
                            src="/images/brand/logo-blanco.png"
                            alt=""
                            width="64"
                            height="64"
                            loading="lazy"
                            className="size-14 shrink-0 object-contain"
                        />
                        <div>
                            <p className="font-serif text-2xl font-bold">
                                Te Pinta
                            </p>
                            <p className="mt-1 max-w-sm text-sm text-white/75">
                                Elegí tus empanadas y armá tu pedido desde el
                                menú.
                            </p>
                        </div>
                    </div>
                    <div className="flex flex-wrap items-center gap-5 text-sm font-semibold">
                        <Link
                            href="/menu"
                            className="inline-flex min-h-11 items-center gap-1 hover:text-public-earth focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-public-earth"
                        >
                            Ver menú{' '}
                            <ArrowUpRight
                                className="size-4"
                                aria-hidden="true"
                            />
                        </Link>
                        <Link
                            href="/cart"
                            className="inline-flex min-h-11 items-center gap-1 hover:text-public-earth focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-public-earth"
                        >
                            Mi carrito{' '}
                            <ArrowUpRight
                                className="size-4"
                                aria-hidden="true"
                            />
                        </Link>
                    </div>
                </div>
            </footer>
        </div>
    );
}
