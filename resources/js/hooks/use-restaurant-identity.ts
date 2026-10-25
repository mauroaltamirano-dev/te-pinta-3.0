import { usePage } from '@inertiajs/react';

/** Name and storefront copy of the installation's restaurant, shared by every Inertia response. */
export interface RestaurantIdentity {
    name: string;
    eyebrow: string | null;
    headline: string | null;
    headline_highlight: string | null;
    intro: string | null;
    home_title: string | null;
    highlights: string[];
    footer_text: string | null;
    location: string | null;
    coming_soon_text: string | null;
    empty_cart_text: string | null;
}

export function useRestaurantIdentity(): RestaurantIdentity {
    return usePage<{ restaurant: RestaurantIdentity }>().props.restaurant;
}

/** Splits a headline around its first highlighted fragment; no fragment, or one not found, keeps it whole. */
export function splitHeadline(
    headline: string,
    highlight: string | null,
): { before: string; highlight: string | null; after: string } {
    const index = highlight ? headline.indexOf(highlight) : -1;

    if (!highlight || index < 0) {
        return { before: headline, highlight: null, after: '' };
    }

    return {
        before: headline.slice(0, index),
        highlight,
        after: headline.slice(index + highlight.length),
    };
}
