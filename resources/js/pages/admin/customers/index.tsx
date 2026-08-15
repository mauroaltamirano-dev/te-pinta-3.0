import { Head, Link, router } from '@inertiajs/react';
import { ArchiveRestore, Plus, Search } from 'lucide-react';
import { useState } from 'react';
import type { FormEvent } from 'react';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { CustomerFormDialog } from '@/features/customers/components/customer-form-dialog';
import type { PaginatedCustomers } from '@/features/customers/types';

const money = new Intl.NumberFormat('es-AR', {
    style: 'currency',
    currency: 'ARS',
    maximumFractionDigits: 0,
});

export default function Customers({
    customers,
    filters,
}: {
    customers: PaginatedCustomers;
    filters: { search: string };
}) {
    const [search, setSearch] = useState(filters.search);
    const [formOpen, setFormOpen] = useState(false);
    const submitSearch = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        router.get('/admin/customers', search ? { search } : {}, {
            preserveState: true,
            replace: true,
        });
    };

    return (
        <>
            <Head title="Customers" />
            <section className="grid gap-6">
                <header className="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                    <div>
                        <p className="text-xs font-semibold tracking-[0.18em] text-admin-accent uppercase">
                            Customer records
                        </p>
                        <h1 className="mt-1 text-2xl font-bold">Customers</h1>
                        <p className="mt-1 text-sm text-muted-foreground">
                            Search profiles and review their order history.
                        </p>
                    </div>
                    <div className="flex flex-wrap gap-2">
                        <Button asChild variant="outline">
                            <Link href="/admin/customers/trash">
                                <ArchiveRestore /> Trash
                            </Link>
                        </Button>
                        <Button type="button" onClick={() => setFormOpen(true)}>
                            <Plus /> Create customer
                        </Button>
                    </div>
                </header>
                <Card>
                    <CardHeader>
                        <form
                            onSubmit={submitSearch}
                            className="flex flex-col gap-2 sm:flex-row"
                            role="search"
                        >
                            <div className="relative flex-1">
                                <Search className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground" />
                                <Input
                                    type="search"
                                    aria-label="Search customers by name or phone"
                                    placeholder="Search by name or phone"
                                    value={search}
                                    onChange={(event) =>
                                        setSearch(event.target.value)
                                    }
                                    className="pl-9"
                                />
                            </div>
                            <Button type="submit" variant="outline">
                                Search
                            </Button>
                        </form>
                    </CardHeader>
                    <CardContent>
                        {customers.data.length === 0 ? (
                            <p
                                role="status"
                                className="py-12 text-center text-sm text-muted-foreground"
                            >
                                No customers match this search.
                            </p>
                        ) : (
                            <div className="overflow-x-auto">
                                <table className="w-full text-left text-sm">
                                    <thead className="border-b text-muted-foreground">
                                        <tr>
                                            <th
                                                scope="col"
                                                className="px-3 py-3 font-medium"
                                            >
                                                Name
                                            </th>
                                            <th
                                                scope="col"
                                                className="px-3 py-3 font-medium"
                                            >
                                                Phone
                                            </th>
                                            <th
                                                scope="col"
                                                className="px-3 py-3 font-medium"
                                            >
                                                Historical spend
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y">
                                        {customers.data.map((customer) => (
                                            <tr key={customer.id}>
                                                <td className="px-3 py-4 font-medium">
                                                    <Link
                                                        className="hover:underline"
                                                        href={`/admin/customers/${customer.id}`}
                                                    >
                                                        {customer.name}
                                                    </Link>
                                                </td>
                                                <td className="px-3 py-4 text-muted-foreground">
                                                    {customer.phone ??
                                                        'Not provided'}
                                                </td>
                                                <td className="px-3 py-4 tabular-nums">
                                                    {money.format(
                                                        customer.historical_spend,
                                                    )}
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        )}
                        <nav
                            aria-label="Customer pages"
                            className="mt-4 flex items-center justify-between border-t pt-4"
                        >
                            <p className="text-xs text-muted-foreground">
                                {customers.total} customers
                            </p>
                            <div className="flex gap-2">
                                {customers.prev_page_url ? (
                                    <Button variant="outline" size="sm" asChild>
                                        <Link href={customers.prev_page_url}>
                                            Previous
                                        </Link>
                                    </Button>
                                ) : (
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        disabled
                                    >
                                        Previous
                                    </Button>
                                )}
                                {customers.next_page_url ? (
                                    <Button variant="outline" size="sm" asChild>
                                        <Link href={customers.next_page_url}>
                                            Next
                                        </Link>
                                    </Button>
                                ) : (
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        disabled
                                    >
                                        Next
                                    </Button>
                                )}
                            </div>
                        </nav>
                    </CardContent>
                </Card>
            </section>
            {formOpen && (
                <CustomerFormDialog
                    open={formOpen}
                    onOpenChange={setFormOpen}
                />
            )}
        </>
    );
}
