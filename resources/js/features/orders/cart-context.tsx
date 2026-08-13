import {
    createContext,
    useContext,
    useMemo,
    useSyncExternalStore,
} from 'react';
import type { PropsWithChildren } from 'react';
import type { CartAddonLine, CartLine } from './types';

interface StoredCart {
    items: CartLine[];
    addons: CartAddonLine[];
    idempotency_key: string;
}
interface CartContextValue extends StoredCart {
    count: number;
    addProduct: (slug: string, quantity: number) => void;
    setProductQuantity: (slug: string, quantity: number) => void;
    setAddonQuantity: (slug: string, quantity: number) => void;
    clear: () => void;
}
const STORAGE_KEY = 'te-pinta-order-cart-v1';
const emptyCart = (idempotencyKey = ''): StoredCart => ({
    items: [],
    addons: [],
    idempotency_key: idempotencyKey,
});
const serverCart = emptyCart();
const listeners = new Set<() => void>();
let browserCart: StoredCart | null = null;
const CartContext = createContext<CartContextValue | null>(null);

export function CartProvider({ children }: PropsWithChildren) {
    const cart = useSyncExternalStore(
        subscribeToCart,
        getCartSnapshot,
        () => serverCart,
    );
    const value = useMemo<CartContextValue>(
        () => ({
            ...cart,
            count: cart.items.reduce((total, item) => total + item.quantity, 0),
            addProduct: (slug, quantity) =>
                updateCart((current) => ({
                    ...current,
                    items: upsert(
                        current.items,
                        'product_slug',
                        slug,
                        quantity,
                    ),
                })),
            setProductQuantity: (slug, quantity) =>
                updateCart((current) => ({
                    ...current,
                    items: replace(
                        current.items,
                        'product_slug',
                        slug,
                        quantity,
                    ),
                })),
            setAddonQuantity: (slug, quantity) =>
                updateCart((current) => ({
                    ...current,
                    addons: replace(
                        current.addons,
                        'addon_slug',
                        slug,
                        quantity,
                    ),
                })),
            clear: () => updateCart(() => emptyCart(crypto.randomUUID())),
        }),
        [cart],
    );

    return (
        <CartContext.Provider value={value}>{children}</CartContext.Provider>
    );
}

function subscribeToCart(listener: () => void) {
    listeners.add(listener);

    return () => listeners.delete(listener);
}

function getCartSnapshot(): StoredCart {
    if (typeof window === 'undefined') {
        return serverCart;
    }

    if (browserCart) {
        return browserCart;
    }

    try {
        const stored = sessionStorage.getItem(STORAGE_KEY);
        browserCart = stored
            ? (JSON.parse(stored) as StoredCart)
            : emptyCart(crypto.randomUUID());
    } catch {
        sessionStorage.removeItem(STORAGE_KEY);
        browserCart = emptyCart(crypto.randomUUID());
    }

    return browserCart;
}

function updateCart(updater: (current: StoredCart) => StoredCart) {
    browserCart = updater(getCartSnapshot());
    sessionStorage.setItem(STORAGE_KEY, JSON.stringify(browserCart));
    listeners.forEach((listener) => listener());
}

function upsert<
    T extends Record<K, string> & { quantity: number },
    K extends keyof T,
>(lines: T[], key: K, value: string, increment: number): T[] {
    const found = lines.find((line) => line[key] === value);

    return found
        ? lines.map((line) =>
              line[key] === value
                  ? { ...line, quantity: line.quantity + increment }
                  : line,
          )
        : [...lines, { [key]: value, quantity: increment } as unknown as T];
}
function replace<
    T extends Record<K, string> & { quantity: number },
    K extends keyof T,
>(lines: T[], key: K, value: string, quantity: number): T[] {
    if (quantity <= 0) {
        return lines.filter((line) => line[key] !== value);
    }

    return lines.some((line) => line[key] === value)
        ? lines.map((line) =>
              line[key] === value ? { ...line, quantity } : line,
          )
        : [...lines, { [key]: value, quantity } as unknown as T];
}
export function useCart() {
    const value = useContext(CartContext);

    if (!value) {
        throw new Error('useCart must be used inside CartProvider.');
    }

    return value;
}
