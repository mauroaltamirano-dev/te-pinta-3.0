import { router, usePage } from '@inertiajs/react';
import { Pencil, Search } from 'lucide-react';
import { useState } from 'react';
import type { FormEvent } from 'react';
import { AdminPageShell } from '@/components/shared/admin-page-shell';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { adminBreadcrumbRoot } from '@/features/admin-navigation/model';
import { CategoryDialog } from '@/features/inventory/components/category-dialog';
import { ItemDialog } from '@/features/inventory/components/item-dialog';
import { PresentationDialog } from '@/features/inventory/components/presentation-dialog';
import {
    formatInventoryQuantity,
    itemKinds,
} from '@/features/inventory/presentation';
import type {
    InventoryCategory,
    InventoryItem,
    InventoryTransaction,
    InventoryUnit,
    Pagination,
    PurchasePresentation,
} from '@/features/inventory/types';
import { formatMoney } from '@/lib/format';

interface Props {
    categories: InventoryCategory[];
    items: InventoryItem[];
    units: InventoryUnit[];
    transactions: Pagination<InventoryTransaction>;
    filters: {
        transaction_id: number | null;
        category: number | null;
        kind: string | null;
        search: string;
    };
    valuation: {
        confident_value: number;
        fully_valued: number;
        partial: number;
        unknown: number;
        negative: number;
        unreliable: number;
        zero: number;
    };
}
const coverageLabels = {
    zero: 'Sin stock valorizable',
    fully_valued: 'Valoración completa',
    partial: 'Cobertura de costo parcial',
    unknown: 'Sin costo conocido',
    negative: 'Stock negativo: valoración no confiable',
    unreliable: 'Costo transferido no confiable',
};
const transactionLabels: Record<string, string> = {
    purchase_receipt: 'Compra',
    production: 'Producción',
    adjustment: 'Ajuste',
    production_consumption: 'Consumo de producción',
    sale_consumption: 'Consumo de venta',
    reversal: 'Reversión',
};

export default function Inventory({
    categories,
    items,
    units,
    transactions,
    filters,
    valuation,
}: Props) {
    const permissions = usePage().props.auth?.permissions ?? [];
    const [categoryEditor, setCategoryEditor] = useState<
        InventoryCategory | 'new' | null
    >(null);
    const [itemEditor, setItemEditor] = useState<InventoryItem | 'new' | null>(
        null,
    );
    const [presentationEditor, setPresentationEditor] = useState<{
        item: InventoryItem;
        presentation?: PurchasePresentation;
    } | null>(null);
    const [search, setSearch] = useState(filters.search);
    const itemFiltersActive = Boolean(
        filters.category || filters.kind || filters.search,
    );
    const visitInventory = (
        query: Partial<Omit<Props['filters'], 'transaction_id'>> & {
            page?: number;
            transaction_id?: number | null;
        } = {},
    ) => {
        const next = {
            category: filters.category,
            kind: filters.kind,
            search: filters.search,
            transaction_id: filters.transaction_id,
            ...query,
        };

        router.get(
            '/admin/inventory',
            Object.fromEntries(
                Object.entries(next).filter(([, value]) => value),
            ),
            { preserveState: true, preserveScroll: true, replace: true },
        );
    };
    const filterItems = (
        query: Partial<Omit<Props['filters'], 'transaction_id'>>,
    ) =>
        visitInventory({
            ...query,
            ...(transactions.current_page > 1
                ? { page: transactions.current_page }
                : {}),
        });
    const visitPage = (page: number) => visitInventory({ page });
    const submitSearch = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        filterItems({ search: search.trim() });
    };
    const clearItemFilters = () => {
        setSearch('');
        filterItems({ category: null, kind: null, search: '' });
    };

    return (
        <AdminPageShell
            title="Inventario físico"
            description="Insumos, presentaciones de compra, stock e historial físico. Configurar datos no registra movimientos."
            breadcrumbs={[
                ...adminBreadcrumbRoot(permissions),
                { title: 'Inventario', href: '/admin/inventory' },
            ]}
            actions={
                <>
                    <Button
                        variant="outline"
                        onClick={() => setCategoryEditor('new')}
                    >
                        Nueva categoría
                    </Button>
                    <Button
                        onClick={() => setItemEditor('new')}
                        disabled={
                            !categories.some((category) => category.is_active)
                        }
                    >
                        Nuevo insumo
                    </Button>
                </>
            }
        >
            <section className="rounded-xl border border-admin-border bg-admin-surface p-4">
                <h2 className="text-lg font-semibold">Valoración actual</h2>
                <p className="mt-2 text-xl font-semibold tabular-nums">
                    {formatMoney(valuation.confident_value)}
                </p>
                <p className="mt-2 text-sm text-admin-text-secondary">
                    Sólo incluye stock con cobertura completa de costo. No es
                    una valoración histórica ni un saldo financiero.
                </p>
                <p className="mt-2 text-sm">
                    {valuation.fully_valued} completos · {valuation.partial}{' '}
                    parciales · {valuation.unknown} sin costo ·{' '}
                    {valuation.negative} negativos · {valuation.unreliable} no
                    confiables
                </p>
            </section>
            <section className="grid gap-3">
                <h2 className="text-lg font-semibold">Categorías</h2>
                {categories.length ? (
                    <div
                        role="group"
                        aria-label="Filtrar insumos por categoría"
                        className="flex flex-wrap gap-2"
                    >
                        <Button
                            variant={filters.category ? 'outline' : 'default'}
                            aria-pressed={!filters.category}
                            onClick={() => filterItems({ category: null })}
                        >
                            Todas
                        </Button>
                        {categories.map((category) => (
                            <div
                                key={category.id}
                                className="flex items-center"
                            >
                                <Button
                                    variant={
                                        filters.category === category.id
                                            ? 'default'
                                            : 'outline'
                                    }
                                    aria-pressed={
                                        filters.category === category.id
                                    }
                                    className="rounded-r-none"
                                    onClick={() =>
                                        filterItems({ category: category.id })
                                    }
                                >
                                    {category.name}
                                    {!category.is_active && ' (inactiva)'}
                                </Button>
                                <Button
                                    variant="outline"
                                    size="icon"
                                    aria-label={`Editar categoría ${category.name}`}
                                    title="Editar categoría"
                                    className="rounded-l-none border-l-0"
                                    onClick={() => setCategoryEditor(category)}
                                >
                                    <Pencil />
                                </Button>
                            </div>
                        ))}
                    </div>
                ) : (
                    <p className="text-sm text-admin-text-secondary">
                        Creá una categoría para comenzar a cargar insumos.
                    </p>
                )}
            </section>
            <section className="grid gap-3">
                <h2 className="text-lg font-semibold">Insumos</h2>
                <div className="flex flex-wrap items-center gap-2">
                    <form
                        onSubmit={submitSearch}
                        role="search"
                        className="flex min-w-0 flex-1 basis-64 gap-2"
                    >
                        <div className="relative min-w-0 flex-1">
                            <Search className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-admin-text-tertiary" />
                            <Input
                                type="search"
                                aria-label="Buscar insumos por nombre"
                                placeholder="Buscar por nombre"
                                value={search}
                                onChange={(event) =>
                                    setSearch(event.target.value)
                                }
                                className="pl-9"
                            />
                        </div>
                        <Button type="submit" variant="outline">
                            Buscar
                        </Button>
                    </form>
                    <select
                        aria-label="Clasificación"
                        value={filters.kind ?? ''}
                        onChange={(event) =>
                            filterItems({ kind: event.target.value || null })
                        }
                        className="h-9 rounded-md border border-input bg-background px-3 text-sm"
                    >
                        <option value="">Todas las clasificaciones</option>
                        {Object.entries(itemKinds).map(([value, label]) => (
                            <option key={value} value={value}>
                                {label}
                            </option>
                        ))}
                    </select>
                    {itemFiltersActive && (
                        <Button variant="ghost" onClick={clearItemFilters}>
                            Limpiar filtros
                        </Button>
                    )}
                </div>
                {items.length ? (
                    <div className="grid gap-4 lg:grid-cols-2">
                        {items.map((item) => (
                            <article
                                key={item.id}
                                aria-label={item.name}
                                className="min-w-0 rounded-xl border border-admin-border bg-admin-surface p-4"
                            >
                                <h3 className="font-semibold break-words">
                                    {item.name}
                                    {!item.is_active && ' (inactivo)'}
                                </h3>
                                <p className="mt-1 text-sm text-admin-text-secondary">
                                    {item.category.name} ·{' '}
                                    {item.kind
                                        ? itemKinds[item.kind]
                                        : 'Clasificación pendiente'}
                                </p>
                                <p className="mt-3 text-lg font-medium tabular-nums">
                                    {item.tracks_stock
                                        ? formatInventoryQuantity(
                                              item.stock_quantity,
                                              item.base_unit,
                                          )
                                        : 'No controla stock'}
                                </p>
                                <p className="mt-1 text-sm">
                                    {coverageLabels[item.valuation.coverage]}
                                    {item.valuation.value !== null &&
                                        ` · ${formatMoney(item.valuation.value)}`}
                                </p>
                                <ul className="mt-3 flex flex-wrap gap-x-4 gap-y-1 text-sm text-admin-text-secondary">
                                    {item.recipe_input_enabled && (
                                        <li>Usar en recetas</li>
                                    )}
                                    {item.production_output_enabled && (
                                        <li>Salida de producción</li>
                                    )}
                                    {item.order_consumption_enabled && (
                                        <li>Consumo al vender</li>
                                    )}
                                </ul>
                                {item.classification_pending && (
                                    <p className="mt-2 text-sm text-admin-text-secondary">
                                        Editá este insumo para definir su
                                        clasificación y capacidades.
                                    </p>
                                )}
                                {item.presentations.length > 0 && (
                                    <ul className="mt-3 grid gap-2 text-sm">
                                        {item.presentations.map(
                                            (presentation) => (
                                                <li
                                                    key={presentation.id}
                                                    className="flex flex-wrap items-center justify-between gap-2"
                                                >
                                                    <span>
                                                        {presentation.name}:{' '}
                                                        {formatInventoryQuantity(
                                                            presentation.content_quantity,
                                                            presentation.content_unit,
                                                        )}
                                                        {!presentation.is_active &&
                                                            ' (inactiva)'}
                                                    </span>
                                                    <Button
                                                        variant="ghost"
                                                        size="sm"
                                                        onClick={() =>
                                                            setPresentationEditor(
                                                                {
                                                                    item,
                                                                    presentation,
                                                                },
                                                            )
                                                        }
                                                    >
                                                        Editar presentación
                                                    </Button>
                                                </li>
                                            ),
                                        )}
                                    </ul>
                                )}
                                <div className="mt-4 flex flex-wrap gap-2">
                                    <Button
                                        variant="outline"
                                        onClick={() => setItemEditor(item)}
                                    >
                                        Editar insumo
                                    </Button>
                                    <Button
                                        variant="outline"
                                        onClick={() =>
                                            setPresentationEditor({ item })
                                        }
                                    >
                                        Presentaciones
                                    </Button>
                                </div>
                            </article>
                        ))}
                    </div>
                ) : itemFiltersActive ? (
                    <p className="text-sm text-admin-text-secondary">
                        Ningún insumo coincide con los filtros.
                    </p>
                ) : (
                    <p className="text-sm text-admin-text-secondary">
                        Todavía no hay insumos. Definí las unidades y
                        capacidades de cada uno antes de registrar operaciones.
                    </p>
                )}
            </section>
            <section className="grid gap-3">
                <div className="flex flex-wrap items-center justify-between gap-2">
                    <h2 className="text-lg font-semibold">Historial físico</h2>
                    {filters.transaction_id && (
                        <Button
                            variant="outline"
                            onClick={() =>
                                visitInventory({ transaction_id: null })
                            }
                        >
                            Ver todo el historial
                        </Button>
                    )}
                </div>
                <p className="text-sm text-admin-text-secondary">
                    Movimientos inmutables en la unidad base de cada insumo. Las
                    reversiones conservan el registro original.
                </p>
                {transactions.data.length ? (
                    <ol className="grid gap-3">
                        {transactions.data.map((transaction) => (
                            <li
                                key={transaction.id}
                                className="rounded-xl border border-admin-border bg-admin-surface p-4"
                            >
                                <h3 className="font-medium">
                                    #{transaction.id} ·{' '}
                                    {transactionLabels[transaction.type] ??
                                        transaction.type}{' '}
                                    · {transaction.effective_date}
                                </h3>
                                {transaction.description && (
                                    <p className="mt-1 text-sm">
                                        {transaction.description}
                                    </p>
                                )}
                                <ul className="mt-2 grid gap-1 text-sm">
                                    {transaction.movements.map((movement) => (
                                        <li key={movement.id}>
                                            {movement.inventory_item.name}:{' '}
                                            {formatInventoryQuantity(
                                                movement.quantity,
                                                movement.inventory_item
                                                    .base_unit,
                                            )}
                                        </li>
                                    ))}
                                </ul>
                            </li>
                        ))}
                    </ol>
                ) : (
                    <p className="text-sm text-admin-text-secondary">
                        Todavía no hay movimientos físicos.
                    </p>
                )}
                {transactions.last_page > 1 && (
                    <nav
                        aria-label="Páginas de historial"
                        className="flex flex-wrap items-center gap-3"
                    >
                        <Button
                            variant="outline"
                            disabled={transactions.current_page <= 1}
                            onClick={() =>
                                visitPage(transactions.current_page - 1)
                            }
                        >
                            Anterior
                        </Button>
                        <span className="text-sm">
                            Página {transactions.current_page} de{' '}
                            {transactions.last_page}
                        </span>
                        <Button
                            variant="outline"
                            disabled={
                                transactions.current_page >=
                                transactions.last_page
                            }
                            onClick={() =>
                                visitPage(transactions.current_page + 1)
                            }
                        >
                            Siguiente
                        </Button>
                    </nav>
                )}
            </section>
            {categoryEditor && (
                <CategoryDialog
                    key={categoryEditor === 'new' ? 'new' : categoryEditor.id}
                    category={
                        categoryEditor === 'new' ? undefined : categoryEditor
                    }
                    onClose={() => setCategoryEditor(null)}
                />
            )}
            {itemEditor && (
                <ItemDialog
                    key={itemEditor === 'new' ? 'new' : itemEditor.id}
                    item={itemEditor === 'new' ? undefined : itemEditor}
                    categories={categories}
                    units={units}
                    onClose={() => setItemEditor(null)}
                />
            )}
            {presentationEditor && (
                <PresentationDialog
                    key={
                        presentationEditor.presentation?.id ??
                        `new-${presentationEditor.item.id}`
                    }
                    {...presentationEditor}
                    units={units}
                    onClose={() => setPresentationEditor(null)}
                />
            )}
        </AdminPageShell>
    );
}
