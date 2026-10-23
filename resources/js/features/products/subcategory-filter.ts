/** One subcategory as a filter option; `key` is the slug publicly and the id in admin screens. */
export interface SubcategoryOption<
    K extends string | number = string | number,
> {
    key: K;
    name: string;
    sort_order: number;
}

/** Subcategories that hold at least one of the given products, in their manual order. */
export function subcategoriesOf<P, K extends string | number>(
    products: P[],
    subcategoryOf: (product: P) => SubcategoryOption<K> | null,
): SubcategoryOption<K>[] {
    const options = new Map<K, SubcategoryOption<K>>();

    for (const product of products) {
        const option = subcategoryOf(product);

        if (option && !options.has(option.key)) {
            options.set(option.key, option);
        }
    }

    return [...options.values()].sort(
        (left, right) =>
            left.sort_order - right.sort_order ||
            left.name.localeCompare(right.name, 'es-AR'),
    );
}

/**
 * Products of the chosen subcategory, in their original order. "all", or a subcategory
 * that no longer holds any of them (for example after a catalog refresh), keeps every product.
 */
export function filterBySubcategory<P, K extends string | number>(
    products: P[],
    subcategoryOf: (product: P) => SubcategoryOption<K> | null,
    selected: K | 'all',
): P[] {
    if (selected === 'all') {
        return products;
    }

    const matching = products.filter(
        (product) => subcategoryOf(product)?.key === selected,
    );

    return matching.length > 0 ? matching : products;
}
