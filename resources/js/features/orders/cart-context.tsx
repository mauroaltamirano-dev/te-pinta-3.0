import { createContext, useContext, useEffect, useMemo, useState } from 'react';
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
const emptyCart = (): StoredCart => ({
    items: [],
    addons: [],
    idempotency_key: crypto.randomUUID(),
});
const CartContext = createContext<CartContextValue | null>(null);

export function CartProvider({ children }: PropsWithChildren) {
    const [cart, setCart] = useState<StoredCart>(() => {
        try {
            const stored = sessionStorage.getItem(STORAGE_KEY);

            return stored ? (JSON.parse(stored) as StoredCart) : emptyCart();
        } catch {
            sessionStorage.removeItem(STORAGE_KEY);

            return emptyCart();
        }
    });
    useEffect(() => {
        sessionStorage.setItem(STORAGE_KEY, JSON.stringify(cart));
    }, [cart]);
    const value = useMemo<CartContextValue>(
        () => ({
            ...cart,
            count: cart.items.reduce((total, item) => total + item.quantity, 0),
            addProduct: (slug, quantity) =>
                setCart((current) => ({
                    ...current,
                    items: upsert(
                        current.items,
                        'product_slug',
                        slug,
                        quantity,
                    ),
                })),
            setProductQuantity: (slug, quantity) =>
                setCart((current) => ({
                    ...current,
                    items: replace(
                        current.items,
                        'product_slug',
                        slug,
                        quantity,
                    ),
                })),
            setAddonQuantity: (slug, quantity) =>
                setCart((current) => ({
                    ...current,
                    addons: replace(
                        current.addons,
                        'addon_slug',
                        slug,
                        quantity,
                    ),
                })),
            clear: () => setCart(emptyCart()),
        }),
        [cart],
    );

    return (
        <CartContext.Provider value={value}>{children}</CartContext.Provider>
    );
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
