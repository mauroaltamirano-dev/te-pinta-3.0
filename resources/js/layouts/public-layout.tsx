import { Link, usePage } from '@inertiajs/react';
import { ShoppingBag } from 'lucide-react';
import type { PropsWithChildren } from 'react';
import { CartProvider, useCart } from '@/features/orders/cart-context';

const navigation = [
    { label: 'Inicio', href: '/' },
    { label: 'Menú', href: '/menu' },
    { label: 'Carrito', href: '/cart' },
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

    return (
        <div className="flex min-h-screen flex-col bg-background text-foreground">
            <header className="border-b bg-background/95 backdrop-blur">
                <nav
                    className="mx-auto flex max-w-6xl items-center justify-between px-4 py-4 sm:px-6"
                    aria-label="Navegación pública"
                >
                    <Link
                        href="/"
                        className="flex shrink-0 items-center gap-2 font-display font-bold tracking-tight"
                    >
                        <img
                            src="/icons/te-pinta-logo.svg"
                            width={512}
                            height={512}
                            alt=""
                            className="size-9 rounded-lg sm:size-10"
                        />
                        <span className="sr-only text-xl sm:not-sr-only">
                            Te Pinta
                        </span>
                    </Link>
                    <div className="flex items-center gap-1 sm:gap-3">
                        {navigation.map((item) => (
                            <Link
                                key={item.href}
                                href={item.href}
                                aria-current={
                                    url === item.href ? 'page' : undefined
                                }
                                className="rounded-full px-3 py-2 text-sm font-medium text-muted-foreground transition-colors hover:bg-muted hover:text-foreground aria-[current=page]:bg-primary aria-[current=page]:text-primary-foreground"
                            >
                                {item.href === '/cart' && (
                                    <ShoppingBag
                                        data-icon="inline-start"
                                        className="mr-1 inline"
                                    />
                                )}
                                {item.label}
                                {item.href === '/cart' && count > 0 && (
                                    <span
                                        className="ml-1 tabular-nums"
                                        aria-label={`${count} productos`}
                                    >
                                        ({count})
                                    </span>
                                )}
                            </Link>
                        ))}
                    </div>
                </nav>
            </header>
            <main className="flex-1">{children}</main>
            <footer className="border-t bg-muted/50">
                <div className="mx-auto grid max-w-6xl gap-3 px-4 py-8 text-sm text-muted-foreground sm:grid-cols-2 sm:px-6">
                    <p className="font-semibold text-foreground">
                        Te Pinta 3.0
                    </p>
                    <p className="sm:text-right">
                        Los pedidos se confirman por WhatsApp. No hay pago en
                        línea.
                    </p>
                </div>
            </footer>
        </div>
    );
}
