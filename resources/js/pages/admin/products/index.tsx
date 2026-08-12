import { Head } from '@inertiajs/react';
import { SlidersHorizontal } from 'lucide-react';
import { useMemo, useState } from 'react';
import { Button } from '@/components/ui/button';
import { AddProductCard } from '@/features/products/components/add-product-card';
import { CategoryManagerDialog } from '@/features/products/components/category-manager-dialog';
import { ProductCard } from '@/features/products/components/product-card';
import { ProductFormDialog } from '@/features/products/components/product-form-dialog';
import type { Product, ProductsPageProps } from '@/features/products/types';
import SettingsLayout from '@/layouts/settings-layout';
import { cn } from '@/lib/utils';

export default function Products({
    categories,
    products,
    tags,
}: ProductsPageProps) {
    const [selectedCategoryId, setSelectedCategoryId] = useState<number | null>(
        categories[0]?.id ?? null,
    );
    const [editingProduct, setEditingProduct] = useState<Product | null>(null);
    const [productDialogOpen, setProductDialogOpen] = useState(false);
    const [categoryDialogOpen, setCategoryDialogOpen] = useState(false);

    const activeCategoryId = categories.some(
        (category) => category.id === selectedCategoryId,
    )
        ? selectedCategoryId
        : (categories[0]?.id ?? null);

    const visibleProducts = useMemo(
        () =>
            products
                .filter((product) => product.category_id === activeCategoryId)
                .sort((left, right) => left.sort_order - right.sort_order),
        [products, activeCategoryId],
    );

    const openCreateDialog = () => {
        setEditingProduct(null);
        setProductDialogOpen(true);
    };

    const openEditDialog = (product: Product) => {
        setEditingProduct(product);
        setProductDialogOpen(true);
    };

    return (
        <>
            <Head title="Products" />
            <SettingsLayout>
                <header className="flex flex-col gap-5 border-b border-admin-border bg-transparent px-5 py-5 sm:px-7">
                    <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            <p className="text-xs font-semibold tracking-[0.18em] text-admin-accent uppercase">
                                Settings
                            </p>
                            <h1 className="mt-1 text-xl font-bold tracking-tight">
                                Products management
                            </h1>
                            <p className="mt-1 max-w-2xl text-sm text-admin-text-secondary">
                                Manage the catalog, base prices, images and sale
                                availability.
                            </p>
                        </div>
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => setCategoryDialogOpen(true)}
                        >
                            <SlidersHorizontal data-icon="inline-start" />
                            Manage categories
                        </Button>
                    </div>

                    <div
                        role="group"
                        aria-label="Product categories"
                        className="flex gap-1 overflow-x-auto"
                    >
                        {categories.map((category) => {
                            const selected = category.id === activeCategoryId;

                            return (
                                <button
                                    key={category.id}
                                    type="button"
                                    aria-pressed={selected}
                                    onClick={() =>
                                        setSelectedCategoryId(category.id)
                                    }
                                    className={cn(
                                        'relative shrink-0 px-3 py-2 text-sm font-medium transition-colors focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none',
                                        selected
                                            ? 'text-admin-accent after:absolute after:right-3 after:bottom-0 after:left-3 after:h-0.5 after:rounded-full after:bg-admin-accent'
                                            : 'text-admin-text-secondary',
                                    )}
                                >
                                    {category.name}
                                    {!category.is_active && (
                                        <span className="ml-1 text-xs opacity-65">
                                            (inactive)
                                        </span>
                                    )}
                                </button>
                            );
                        })}
                    </div>
                </header>

                <div className="bg-transparent p-5 sm:p-7">
                    {categories.length === 0 ? (
                        <div className="flex min-h-72 flex-col items-center justify-center gap-4 rounded-xl border border-dashed border-border p-8 text-center">
                            <h2 className="font-semibold">
                                Create a category first
                            </h2>
                            <p className="max-w-sm text-sm text-muted-foreground">
                                Every product belongs to one category. Add the
                                first category before creating catalog items.
                            </p>
                            <Button
                                type="button"
                                onClick={() => setCategoryDialogOpen(true)}
                            >
                                Manage categories
                            </Button>
                        </div>
                    ) : (
                        <div className="grid auto-rows-fr gap-4 sm:grid-cols-2 xl:grid-cols-3 2xl:grid-cols-4">
                            <AddProductCard onClick={openCreateDialog} />
                            {visibleProducts.map((product) => (
                                <ProductCard
                                    key={product.id}
                                    product={product}
                                    onEdit={openEditDialog}
                                />
                            ))}
                        </div>
                    )}
                </div>
            </SettingsLayout>

            {productDialogOpen && (
                <ProductFormDialog
                    open={productDialogOpen}
                    onOpenChange={setProductDialogOpen}
                    product={editingProduct}
                    categories={categories}
                    tags={tags}
                    defaultCategoryId={activeCategoryId}
                />
            )}
            <CategoryManagerDialog
                open={categoryDialogOpen}
                onOpenChange={setCategoryDialogOpen}
                categories={categories}
            />
        </>
    );
}
