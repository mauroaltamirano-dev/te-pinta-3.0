import { Head, Link, router } from '@inertiajs/react';
import {
    ArchiveRestore,
    ChevronLeft,
    ChevronRight,
    Plus,
    Search,
} from 'lucide-react';
import { useState } from 'react';
import type { FormEvent } from 'react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { CustomerFormDialog } from '@/features/customers/components/customer-form-dialog';
import { CustomerList } from '@/features/customers/components/customer-list';
import type {
    Customer,
    CustomerSort,
    CustomerSortDirection,
    PaginatedCustomers,
} from '@/features/customers/types';

interface CustomerFilters {
    search: string;
    sort: CustomerSort;
    direction: CustomerSortDirection;
}

export default function Customers({
    customers,
    filters,
}: {
    customers: PaginatedCustomers;
    filters: CustomerFilters;
}) {
    const [search, setSearch] = useState(filters.search);
    const [formOpen, setFormOpen] = useState(false);
    const [editingCustomer, setEditingCustomer] = useState<Customer | null>(
        null,
    );
    const navigationParams = (
        sort: CustomerSort = filters.sort,
        direction: CustomerSortDirection = filters.direction,
    ) => ({
        ...(search ? { search } : {}),
        sort,
        direction,
    });
    const navigate = (sort: CustomerSort, direction: CustomerSortDirection) =>
        router.get('/admin/customers', navigationParams(sort, direction), {
            preserveState: true,
            replace: true,
        });
    const submitSearch = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        router.get('/admin/customers', navigationParams(), {
            preserveState: true,
            replace: true,
        });
    };
    const sortCustomers = (sort: CustomerSort) =>
        navigate(
            sort,
            filters.sort === sort && filters.direction === 'asc'
                ? 'desc'
                : 'asc',
        );
    const selectMobileSort = (value: string) => {
        const [sort, direction] = value.split(':') as [
            CustomerSort,
            CustomerSortDirection,
        ];
        navigate(sort, direction);
    };
    const edit = (customer: Customer) => {
        setEditingCustomer(customer);
        setFormOpen(true);
    };
    const closeForm = (open: boolean) => {
        setFormOpen(open);

        if (!open) {
            setEditingCustomer(null);
        }
    };

    return (
        <>
            <Head title="Clientes" />
            <section className="grid gap-6 font-orders">
                <header className="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                    <div>
                        <p className="text-sm font-medium text-admin-accent">
                            Gestión de clientes
                        </p>
                        <h1 className="mt-1 text-3xl font-semibold tracking-tight text-white">
                            Clientes
                        </h1>
                        <p className="mt-1 text-sm text-admin-text-tertiary">
                            Encontrá datos de contacto y revisá cada historial
                            de pedidos.
                        </p>
                    </div>
                    <div className="grid grid-cols-2 gap-2 sm:flex">
                        <Button
                            asChild
                            variant="outline"
                            className="h-11 rounded-lg px-4"
                        >
                            <Link href="/admin/customers/trash">
                                <ArchiveRestore /> Papelera
                            </Link>
                        </Button>
                        <Button
                            type="button"
                            onClick={() => setFormOpen(true)}
                            className="h-11 rounded-lg px-4"
                        >
                            <Plus /> Crear cliente
                        </Button>
                    </div>
                </header>

                <div className="overflow-hidden rounded-lg bg-admin-surface">
                    <div className="border-b border-admin-border p-4 sm:p-5">
                        <form
                            onSubmit={submitSearch}
                            className="flex gap-2"
                            role="search"
                        >
                            <div className="relative min-w-0 flex-1">
                                <Search className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-admin-text-tertiary" />
                                <Input
                                    type="search"
                                    aria-label="Buscar clientes por nombre o teléfono"
                                    placeholder="Buscar por nombre o teléfono"
                                    value={search}
                                    onChange={(event) =>
                                        setSearch(event.target.value)
                                    }
                                    className="h-11 border-admin-border bg-admin-canvas pl-9"
                                />
                            </div>
                            <Button
                                type="submit"
                                variant="outline"
                                className="h-11 px-4"
                            >
                                Buscar
                            </Button>
                        </form>
                        <div className="mt-3 flex items-center justify-between gap-3">
                            <p
                                className="text-sm text-admin-text-tertiary"
                                role="status"
                            >
                                {customers.total}{' '}
                                {customers.total === 1 ? 'cliente' : 'clientes'}
                            </p>
                            <Select
                                value={`${filters.sort}:${filters.direction}`}
                                onValueChange={selectMobileSort}
                            >
                                <SelectTrigger
                                    aria-label="Orden de clientes"
                                    className="h-9 w-[11.5rem] border-admin-border bg-admin-canvas md:hidden"
                                >
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="name:asc">
                                        Nombre A–Z
                                    </SelectItem>
                                    <SelectItem value="name:desc">
                                        Nombre Z–A
                                    </SelectItem>
                                    <SelectItem value="orders_count:desc">
                                        Más pedidos
                                    </SelectItem>
                                    <SelectItem value="orders_count:asc">
                                        Menos pedidos
                                    </SelectItem>
                                    <SelectItem value="historical_spend:desc">
                                        Mayor gasto
                                    </SelectItem>
                                    <SelectItem value="historical_spend:asc">
                                        Menor gasto
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                        </div>
                    </div>

                    {customers.data.length === 0 ? (
                        <p
                            role="status"
                            className="px-5 py-14 text-center text-sm text-admin-text-tertiary"
                        >
                            No encontramos clientes con esa búsqueda.
                        </p>
                    ) : (
                        <CustomerList
                            customers={customers.data}
                            onEdit={edit}
                            sort={filters.sort}
                            direction={filters.direction}
                            onSort={sortCustomers}
                        />
                    )}

                    <nav
                        aria-label="Paginación de clientes"
                        className="flex items-center justify-between border-t border-admin-border px-4 py-4 sm:px-5"
                    >
                        <p className="text-sm text-admin-text-tertiary">
                            Página {customers.current_page} de{' '}
                            {customers.last_page}
                        </p>
                        <div className="flex gap-2">
                            <Button
                                asChild={Boolean(customers.prev_page_url)}
                                variant="outline"
                                size="icon"
                                disabled={!customers.prev_page_url}
                                aria-label="Página anterior"
                            >
                                {customers.prev_page_url ? (
                                    <Link href={customers.prev_page_url}>
                                        <ChevronLeft />
                                    </Link>
                                ) : (
                                    <span>
                                        <ChevronLeft />
                                    </span>
                                )}
                            </Button>
                            <Button
                                asChild={Boolean(customers.next_page_url)}
                                variant="outline"
                                size="icon"
                                disabled={!customers.next_page_url}
                                aria-label="Página siguiente"
                            >
                                {customers.next_page_url ? (
                                    <Link href={customers.next_page_url}>
                                        <ChevronRight />
                                    </Link>
                                ) : (
                                    <span>
                                        <ChevronRight />
                                    </span>
                                )}
                            </Button>
                        </div>
                    </nav>
                </div>
            </section>
            {formOpen && (
                <CustomerFormDialog
                    open={formOpen}
                    onOpenChange={closeForm}
                    customer={editingCustomer ?? undefined}
                />
            )}
        </>
    );
}
