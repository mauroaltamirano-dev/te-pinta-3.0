import { Head } from '@inertiajs/react';
import { Plus, Search, SlidersHorizontal, Tags } from 'lucide-react';
import { useMemo, useState } from 'react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { ProductFeaturedScheduler } from '@/features/marketing/components/product-featured-scheduler';
import { CategoryManagerDialog } from '@/features/products/components/category-manager-dialog';
import { ProductCard } from '@/features/products/components/product-card';
import { ProductFormDialog } from '@/features/products/components/product-form-dialog';
import { TagManagerDialog } from '@/features/products/components/tag-manager-dialog';
import type { Product, ProductsPageProps } from '@/features/products/types';
import SettingsLayout from '@/layouts/settings-layout';
import { cn } from '@/lib/utils';

/** Subcategory filter: all products, those without one, or one subcategory id. */
type SubcategoryFilter = 'all' | 'none' | number;

const chipClass =
    'inline-flex h-8 shrink-0 items-center gap-1.5 rounded-full border border-admin-border px-3 text-xs font-medium text-admin-text-secondary transition-colors focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none aria-[pressed=true]:border-admin-accent aria-[pressed=true]:bg-admin-selection aria-[pressed=true]:text-admin-accent-text';

export default function Products({
    categories,
    products,
    tags,
    empanadaUnitSurchargePercent,
}: ProductsPageProps) {
    const [selectedCategoryId, setSelectedCategoryId] = useState<number | null>(
        categories[0]?.id ?? null,
    );
    const [subcategoryFilter, setSubcategoryFilter] =
        useState<SubcategoryFilter>('all');
    const [search, setSearch] = useState('');
    const [editingProduct, setEditingProduct] = useState<Product | null>(null);
    const [productDialogOpen, setProductDialogOpen] = useState(false);
    const [categoryDialogOpen, setCategoryDialogOpen] = useState(false);
    const [tagDialogOpen, setTagDialogOpen] = useState(false);
    const [featuredProduct, setFeaturedProduct] = useState<Product | null>(
        null,
    );

    const activeCategory =
        categories.find((category) => category.id === selectedCategoryId) ??
        categories[0] ??
        null;
    const categoryProducts = useMemo(
        () =>
            products
                .filter((product) => product.category_id === activeCategory?.id)
                .sort((left, right) => left.sort_order - right.sort_order),
        [products, activeCategory],
    );
    const subcategories = activeCategory?.subcategories ?? [];
    const activeSubcategoryFilter =
        typeof subcategoryFilter === 'number' &&
        !subcategories.some(
            (subcategory) => subcategory.id === subcategoryFilter,
        )
            ? 'all'
            : subcategoryFilter;
    const withoutSubcategory = categoryProducts.filter(
        (product) => product.subcategory_id === null,
    ).length;
    const term = search.trim().toLocaleLowerCase('es-AR');
    // A search spans the whole catalog: the product may live in another category.
    const visibleProducts =
        term !== ''
            ? products.filter((product) =>
                  product.name.toLocaleLowerCase('es-AR').includes(term),
              )
            : categoryProducts.filter(
                  (product) =>
                      activeSubcategoryFilter === 'all' ||
                      (activeSubcategoryFilter === 'none'
                          ? product.subcategory_id === null
                          : product.subcategory_id === activeSubcategoryFilter),
              );

    const locationOf = (product: Product) => {
        const category = categories.find(
            (item) => item.id === product.category_id,
        );
        const subcategory = category?.subcategories.find(
            (item) => item.id === product.subcategory_id,
        );

        return [category?.name, subcategory?.name].filter(Boolean).join(' › ');
    };

    const selectCategory = (categoryId: number) => {
        setSelectedCategoryId(categoryId);
        setSubcategoryFilter('all');
        setSearch('');
    };

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
                    <div className="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                        <div>
                            <p className="text-xs font-semibold tracking-[0.18em] text-admin-accent-text uppercase">
                                Catálogo
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
                                onClick={openCreateDialog}
                                disabled={categories.length === 0}
                            >
                                <Plus data-icon="inline-start" />
                                Agregar producto
                            </Button>
                            <Button
                                type="button"
                                variant="outline"
                                onClick={() => setCategoryDialogOpen(true)}
                            >
                                <SlidersHorizontal data-icon="inline-start" />
                                Gestionar categorías
                            </Button>
                            <Button
                                type="button"
                                variant="outline"
                                onClick={() => setTagDialogOpen(true)}
                            >
                                <Tags data-icon="inline-start" /> Gestionar
                                etiquetas
                            </Button>
                        </div>
                    </div>

                    {categories.length > 0 && (
                        <div className="flex flex-col gap-3">
                            <div className="relative max-w-sm">
                                <Search
                                    aria-hidden="true"
                                    className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-admin-text-tertiary"
                                />
                                <Input
                                    type="search"
                                    aria-label="Buscar producto"
                                    placeholder="Buscar en todo el catálogo"
                                    value={search}
                                    onChange={(event) =>
                                        setSearch(event.target.value)
                                    }
                                    className="pl-9"
                                />
                            </div>
                            <div
                                role="group"
                                aria-label="Categorías de productos"
                                className={cn(
                                    'flex gap-1 overflow-x-auto',
                                    term !== '' && 'opacity-50',
                                )}
                            >
                                {categories.map((category) => {
                                    const selected =
                                        term === '' &&
                                        category.id === activeCategory?.id;
                                    const count = products.filter(
                                        (product) =>
                                            product.category_id === category.id,
                                    ).length;

                                    return (
                                        <button
                                            key={category.id}
                                            type="button"
                                            aria-pressed={selected}
                                            onClick={() =>
                                                selectCategory(category.id)
                                            }
                                            className={cn(
                                                'relative shrink-0 px-3 py-2 text-sm font-medium transition-colors focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none',
                                                selected
                                                    ? 'text-admin-accent-text after:absolute after:right-3 after:bottom-0 after:left-3 after:h-0.5 after:rounded-full after:bg-admin-accent'
                                                    : 'text-admin-text-secondary',
                                            )}
                                        >
                                            {category.name}
                                            {!category.is_active && (
                                                <span className="ml-1 text-xs opacity-65">
                                                    (inactiva)
                                                </span>
                                            )}
                                            <span
                                                aria-hidden="true"
                                                className="ml-1.5 text-xs text-admin-text-tertiary tabular-nums"
                                            >
                                                {count}
                                            </span>
                                        </button>
                                    );
                                })}
                            </div>
                            {term === '' && subcategories.length > 0 && (
                                <div
                                    role="group"
                                    aria-label="Subcategorías"
                                    className="flex gap-2 overflow-x-auto pb-1"
                                >
                                    <button
                                        type="button"
                                        aria-pressed={
                                            activeSubcategoryFilter === 'all'
                                        }
                                        onClick={() =>
                                            setSubcategoryFilter('all')
                                        }
                                        className={chipClass}
                                    >
                                        Todas
                                    </button>
                                    {subcategories.map((subcategory) => (
                                        <button
                                            key={subcategory.id}
                                            type="button"
                                            aria-pressed={
                                                activeSubcategoryFilter ===
                                                subcategory.id
                                            }
                                            onClick={() =>
                                                setSubcategoryFilter(
                                                    subcategory.id,
                                                )
                                            }
                                            className={chipClass}
                                        >
                                            {subcategory.name}
                                            {!subcategory.is_active &&
                                                ' (inactiva)'}
                                        </button>
                                    ))}
                                    {withoutSubcategory > 0 && (
                                        <button
                                            type="button"
                                            aria-pressed={
                                                activeSubcategoryFilter ===
                                                'none'
                                            }
                                            onClick={() =>
                                                setSubcategoryFilter('none')
                                            }
                                            className={chipClass}
                                        >
                                            Sin subcategoría
                                            <span
                                                aria-hidden="true"
                                                className="tabular-nums opacity-70"
                                            >
                                                {withoutSubcategory}
                                            </span>
                                        </button>
                                    )}
                                </div>
                            )}
                        </div>
                    )}
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
                    ) : visibleProducts.length === 0 ? (
                        <p
                            role="status"
                            className="rounded-xl border border-dashed border-border p-8 text-center text-sm text-muted-foreground"
                        >
                            {term !== ''
                                ? 'Ningún producto coincide con la búsqueda.'
                                : 'No hay productos en esta selección.'}
                        </p>
                    ) : (
                        <div className="grid gap-2">
                            {visibleProducts.map((product) => (
                                <ProductCard
                                    key={product.id}
                                    product={product}
                                    location={locationOf(product)}
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
                    defaultCategoryId={activeCategory?.id ?? null}
                    defaultSubcategoryId={
                        subcategories.find(
                            (subcategory) =>
                                subcategory.is_active &&
                                subcategory.id === activeSubcategoryFilter,
                        )?.id ?? null
                    }
                    empanadaUnitSurchargePercent={empanadaUnitSurchargePercent}
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
