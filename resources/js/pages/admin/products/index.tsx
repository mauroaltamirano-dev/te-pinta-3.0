import { Head } from '@inertiajs/react';
import { SlidersHorizontal, Tags } from 'lucide-react';
import { useMemo, useState } from 'react';
import { Button } from '@/components/ui/button';
import { ProductFeaturedScheduler } from '@/features/marketing/components/product-featured-scheduler';
import { AddProductCard } from '@/features/products/components/add-product-card';
import { CategoryManagerDialog } from '@/features/products/components/category-manager-dialog';
import { ProductCard } from '@/features/products/components/product-card';
import { ProductFormDialog } from '@/features/products/components/product-form-dialog';
import { TagManagerDialog } from '@/features/products/components/tag-manager-dialog';
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
    const [tagDialogOpen, setTagDialogOpen] = useState(false);
    const [featuredProduct, setFeaturedProduct] = useState<Product | null>(
        null,
    );

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
            <Head title="Productos" />
            <SettingsLayout>
                <header className="flex flex-col gap-5 border-b border-admin-border bg-transparent px-5 py-5 sm:px-7">
                    <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            <p className="text-xs font-semibold tracking-[0.18em] text-admin-accent uppercase">
                                Configuración
                            </p>
                            <h1 className="mt-1 text-xl font-bold tracking-tight">
                                Gestión de productos
                            </h1>
                            <p className="mt-1 max-w-2xl text-sm text-admin-text-secondary">
                                Administrá el catálogo, los precios cocinados,
                                las imágenes y la disponibilidad de venta.
                            </p>
                        </div>
                        <div className="flex flex-col gap-2 sm:flex-row">
                            <Button
                                type="button"
                                variant="outline"
                                onClick={() => setTagDialogOpen(true)}
                                className="w-full sm:w-auto"
                            >
                                <Tags data-icon="inline-start" /> Gestionar
                                etiquetas
                            </Button>
                            <Button
                                type="button"
                                variant="outline"
                                onClick={() => setCategoryDialogOpen(true)}
                            >
                                <SlidersHorizontal data-icon="inline-start" />
                                Gestionar categorías
                            </Button>
                        </div>
                    </div>

                    <div
                        role="group"
                        aria-label="Categorías de productos"
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
                                            (inactiva)
                                        </span>
                                    )}
                                </button>
                            );
                        })}
                    </div>
                </header>

                <div className="bg-transparent p-5 sm:p-7">
                    {featuredProduct && (
                        <ProductFeaturedScheduler
                            key={featuredProduct.id}
                            product={
                                products.find(
                                    (product) =>
                                        product.id === featuredProduct.id,
                                ) ?? featuredProduct
                            }
                            onClose={() => setFeaturedProduct(null)}
                        />
                    )}
                    {categories.length === 0 ? (
                        <div className="flex min-h-72 flex-col items-center justify-center gap-4 rounded-xl border border-dashed border-border p-8 text-center">
                            <h2 className="font-semibold">
                                Creá primero una categoría
                            </h2>
                            <p className="max-w-sm text-sm text-muted-foreground">
                                Cada producto pertenece a una categoría. Creá la
                                primera antes de cargar productos al catálogo.
                            </p>
                            <Button
                                type="button"
                                onClick={() => setCategoryDialogOpen(true)}
                            >
                                Gestionar categorías
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
                                    onFeature={setFeaturedProduct}
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
            <TagManagerDialog
                open={tagDialogOpen}
                onOpenChange={setTagDialogOpen}
                tags={tags}
            />
        </>
    );
}
