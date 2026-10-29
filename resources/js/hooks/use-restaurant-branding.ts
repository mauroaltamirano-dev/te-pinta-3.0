import { usePage } from '@inertiajs/react';
import { useEffect, useLayoutEffect } from 'react';
import { useRestaurantIdentity } from '@/hooks/use-restaurant-identity';

interface RestaurantBrand {
    accent: string;
    variables: Record<string, string>;
}

export function useRestaurantBranding() {
    const { brand } = usePage<{ brand: RestaurantBrand }>().props;
    const { name } = useRestaurantIdentity();

    // The title callback reads the server-rendered name; a layout effect updates it
    // before the page's <Head> (a passive effect), so a rename shows without a reload.
    // <Head> does not rerun when only the name changes, so the current title is renamed too.
    useLayoutEffect(() => {
        const meta = document.querySelector('meta[name="application-name"]');
        const previous = meta?.getAttribute('content') ?? '';

        if (!meta || previous === name) {
            return;
        }

        meta.setAttribute('content', name);

        if (document.title === previous) {
            document.title = name;
        } else if (previous && document.title.endsWith(` - ${previous}`)) {
            document.title = `${document.title.slice(0, -previous.length)}${name}`;
        }
    }, [name]);

    useEffect(() => {
        for (const [token, value] of Object.entries(brand.variables)) {
            document.documentElement.style.setProperty(token, value);
        }

        document
            .querySelector('meta[name="theme-color"]')
            ?.setAttribute('content', brand.accent);
    }, [brand]);
}
