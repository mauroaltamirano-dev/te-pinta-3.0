export interface FeaturedProductPriceView {
    unit: number;
    half_dozen: number;
    dozen: number;
}

export interface FeaturedProductProductView {
    slug: string;
    name: string;
    description: string | null;
    image_url: string | null;
    prices: FeaturedProductPriceView;
}

export interface FeaturedProductPublicView {
    headline: string | null;
    short_copy: string | null;
    product: FeaturedProductProductView;
}

export type FeaturedProductStatus =
    'inactive' | 'scheduled' | 'active' | 'finished';

export type FeaturedProductVisibilityReason =
    'product_unavailable' | 'product_deleted' | 'category_unavailable' | null;

export interface AdminFeaturedProduct {
    id: number;
    headline: string | null;
    short_copy: string | null;
    starts_at: string;
    ends_at: string | null;
    starts_at_local: string;
    ends_at_local: string | null;
    is_enabled: boolean;
    status: FeaturedProductStatus;
    is_publicly_visible: boolean;
    visibility_reason: FeaturedProductVisibilityReason;
    product: {
        id: number;
        name: string;
        is_deleted: boolean;
    };
    public_view: FeaturedProductPublicView;
}

export interface SelectableFeaturedProduct {
    id: number;
    product: FeaturedProductProductView;
}

export interface FeaturedProductFormDefaults {
    starts_at: string;
    ends_at: null;
    is_enabled: boolean;
}

export interface MarketingPageProps {
    current: AdminFeaturedProduct | null;
    upcoming: AdminFeaturedProduct[];
    history: AdminFeaturedProduct[];
    selectable_products: SelectableFeaturedProduct[];
    form_defaults: FeaturedProductFormDefaults;
    business_timezone: string;
}

export type MarketingDashboardSummary = {
    state: 'active' | 'active_hidden' | 'upcoming' | 'none';
    business_timezone: string;
    product_name: string | null;
    starts_at: string | null;
    ends_at: string | null;
    visibility_reason: FeaturedProductVisibilityReason;
};
