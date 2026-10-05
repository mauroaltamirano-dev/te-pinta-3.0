export interface ProductCategory {
    id: number;
    name: string;
    slug: string;
    sort_order: number;
    is_active: boolean;
}

export interface ProductTag {
    id: number;
    name: string;
    slug: string;
}

export interface Product {
    id: number;
    slug: string;
    name: string;
    description: string | null;
    long_description: string | null;
    public_ingredients: string | null;
    image_url: string | null;
    category_id: number;
    pricing_family: 'empanada' | 'unit';
    tags: ProductTag[];
    unit_price: number;
    half_dozen_price: number;
    dozen_price: number;
    is_available: boolean;
    sort_order: number;
}

export interface ProductsPageProps {
    categories: ProductCategory[];
    products: Product[];
    tags: ProductTag[];
}
