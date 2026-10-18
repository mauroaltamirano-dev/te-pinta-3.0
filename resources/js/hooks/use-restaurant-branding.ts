import { usePage } from '@inertiajs/react';
import { useEffect } from 'react';

interface RestaurantBrand {
    accent: string;
    variables: Record<string, string>;
}

export function useRestaurantBranding() {
    const { brand } = usePage<{ brand: RestaurantBrand }>().props;

    useEffect(() => {
        for (const [token, value] of Object.entries(brand.variables)) {
            document.documentElement.style.setProperty(token, value);
        }

        document
            .querySelector('meta[name="theme-color"]')
            ?.setAttribute('content', brand.accent);
    }, [brand]);
}
