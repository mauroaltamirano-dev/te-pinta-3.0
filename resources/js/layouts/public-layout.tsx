import { Link, usePage } from '@inertiajs/react';
import { ArrowUpRight, ShoppingBag } from 'lucide-react';
import type { PropsWithChildren } from 'react';
import { CartProvider, useCart } from '@/features/orders/cart-context';

const navigation = [
    { label: 'Inicio', href: '/' },
    { label: 'Menú', href: '/menu' },
];

export default function PublicLayout({ children }: PropsWithChildren) {
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

    return (
        <div className="public-theme public-shell flex min-h-screen flex-col bg-background font-sans text-foreground">
            <a
                href="#main-content"
                className="sr-only rounded-md bg-primary px-4 py-2 text-primary-foreground focus:not-sr-only focus:absolute focus:z-50 focus:m-3"
            >
                Ir al contenido
            </a>
            <header className="relative z-10 border-b border-border/70 bg-background">
                <nav
                    className="mx-auto flex min-h-18 max-w-7xl items-center justify-between gap-3 px-4 sm:px-8 lg:min-h-22 lg:px-12"
                    aria-label="Navegación pública"
                >
                    <Link
                        href="/"
                        className="shrink-0 rounded-md font-display text-2xl font-black tracking-tight outline-none focus-visible:ring-2 focus-visible:ring-ring sm:text-3xl"
                        aria-label="Te Pinta, inicio"
                    >
                        <span>
                            Te Pinta<span className="text-primary">.</span>
                        </span>
                    </Link>
                    <div className="flex items-center gap-1 sm:gap-3">
                        {navigation.map((item) => (
                            <Link
                                key={item.href}
                                href={item.href}
                                aria-current={
                                    pathname === item.href ? 'page' : undefined
                                }
                                className="hidden min-h-11 items-center rounded-full px-4 text-sm font-semibold transition-colors hover:text-public-earth-ink focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring aria-[current=page]:text-public-earth-ink sm:inline-flex"
                            >
                                {item.label}
                            </Link>
                        ))}
                        <Link
                            href="/menu"
                            aria-current={
                                pathname === '/menu' ? 'page' : undefined
                            }
                            className="inline-flex min-h-11 items-center rounded-full px-3 text-sm font-semibold transition-colors hover:text-public-earth-ink focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring aria-[current=page]:text-public-earth-ink sm:hidden"
                        >
                            Menú
                        </Link>
                        <Link
                            href="/cart"
                            aria-current={
                                pathname === '/cart' ? 'page' : undefined
                            }
                            aria-label={
                                count > 0
                                    ? `Carrito, ${count} productos`
                                    : 'Carrito'
                            }
                            className="inline-flex min-h-11 items-center gap-2 rounded-full border border-foreground bg-foreground px-4 text-sm font-semibold text-background transition-colors hover:bg-primary hover:text-white focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring aria-[current=page]:border-primary aria-[current=page]:bg-primary aria-[current=page]:text-white sm:px-5"
                        >
                            <ShoppingBag
                                className="size-4"
                                aria-hidden="true"
                            />
                            <span className="hidden min-[360px]:inline">
                                Carrito
                            </span>
                            {count > 0 && (
                                <span
                                    className="grid min-w-5 place-items-center rounded-full bg-background px-1 text-xs font-bold text-foreground tabular-nums"
                                    aria-label={`${count} productos`}
                                >
                                    {count}
                                </span>
                            )}
                        </Link>
                    </div>
                </nav>
            </header>
            <main id="main-content" className="flex-1">
                {children}
            </main>
            <footer className="border-t border-border bg-foreground text-background">
                <div className="mx-auto grid max-w-7xl gap-8 px-4 py-10 sm:grid-cols-[1fr_auto] sm:items-end sm:px-8 lg:px-12">
                    <div>
                        <p className="font-display text-3xl font-black tracking-tight">
                            Te Pinta<span className="text-primary">.</span>
                        </p>
                        <p className="mt-2 max-w-sm text-sm text-background/75">
                            Elegí tus empanadas y armá tu pedido desde el menú.
                        </p>
                    </div>
                    <div className="flex flex-wrap items-center gap-5 text-sm font-semibold">
                        <Link
                            href="/menu"
                            className="inline-flex min-h-11 items-center gap-1 hover:text-white focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary"
                        >
                            Ver menú{' '}
                            <ArrowUpRight
                                className="size-4"
                                aria-hidden="true"
                            />
                        </Link>
                        <Link
                            href="/cart"
                            className="inline-flex min-h-11 items-center gap-1 hover:text-white focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary"
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
